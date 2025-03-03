<?php

namespace App\Filters;

use App\Models\Attribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProjectFilter extends QueryFilter
{
    public function name($value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        // Default to ILIKE for text search if no specific operator
        if ($operator === '=' || $operator === 'LIKE') {
            $operator = 'ILIKE';
            if (!Str::contains($value, '%')) {
                $value = "%{$value}%";
            }
        }
        
        $this->builder->where('name', $operator, $value);
    }

    public function description($value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        // Default to ILIKE for text search if no specific operator
        if ($operator === '=' || $operator === 'LIKE') {
            $operator = 'ILIKE';
            if (!Str::contains($value, '%')) {
                $value = "%{$value}%";
            }
        }
        
        $this->builder->where('description', $operator, $value);
    }

    public function status($value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        if ($operator === 'LIKE' || $operator === '=') {
            $operator = 'ILIKE';
            if (!Str::contains($value, '%')) {
                $value = "%{$value}%";
            }
        }
        $this->builder->where('status', $operator, $value);
    }

    protected function handleDateFilter(string $field, $value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        // If no value provided, don't apply the filter
        if (empty($value)) {
            return;
        }

        try {
            // Parse the date value
            $date = Carbon::parse($value)->toDateString();

            // Handle date comparison
            $this->builder->whereDate($field, $operator, $date);

            Log::info('Date filter applied', [
                'field' => $field,
                'operator' => $operator,
                'value' => $date,
                'sql' => $this->builder->toSql(),
                'bindings' => $this->builder->getBindings()
            ]);
        } catch (\Exception $e) {
            Log::warning('Invalid date format', [
                'field' => $field,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function createdAt($value): void
    {
        $this->handleDateFilter('created_at', $value);
    }

    public function updatedAt($value): void
    {
        $this->handleDateFilter('updated_at', $value);
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;
        
        foreach ($this->filters() as $field => $value) {
            // Convert field names to camelCase for method names
            $method = Str::camel($field);
            if (method_exists($this, $method)) {
                call_user_func_array([$this, $method], [$value]);
                continue;
            }

            // Handle EAV attributes - case-insensitive search for attribute name
            $attribute = Attribute::where('name', 'ILIKE', $field)->first();
            
            Log::info('Looking up attribute', [
                'field' => $field,
                'attribute_found' => $attribute ? true : false,
                'attribute_id' => $attribute?->id
            ]);

            if ($attribute) {
                [$operator, $filterValue] = $this->parseOperator($value);

                // Default to ILIKE for text search if no specific operator
                if ($operator === '=' || $operator === 'LIKE') {
                    $operator = 'ILIKE';
                    if (!Str::contains($filterValue, '%')) {
                        $filterValue = "%{$filterValue}%";
                    }
                }

                $this->builder->whereHas('attributeValues', function ($query) use ($attribute, $operator, $filterValue) {
                    $query->where('attribute_id', $attribute->id)
                          ->where('value', $operator, $filterValue)
                          ->where('entity_type', 'App\\Models\\Project');

                    Log::info('Attribute value query', [
                        'attribute_id' => $attribute->id,
                        'operator' => $operator,
                        'value' => $filterValue,
                        'sql' => $query->toSql(),
                        'bindings' => $query->getBindings()
                    ]);
                });
            }
        }

        Log::info('Final project filter query', [
            'sql' => $this->builder->toSql(),
            'bindings' => $this->builder->getBindings(),
            'filters' => $this->filters()
        ]);

        return $this->builder;
    }
} 