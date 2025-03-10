<?php

namespace App\Filters;

use App\Models\Attribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProjectFilter extends QueryFilter
{
    /**
     * @var array Collection of EAV filters to be applied
     */
    protected array $eavFilters = [];

    /**
     * @var FilterValidator
     */
    protected FilterValidator $validator;

    /**
     * Constructor
     *
     * @param Request $request
     * @param FilterValidator $validator
     */
    public function __construct(Request $request, FilterValidator $validator)
    {
        parent::__construct($request);
        $this->validator = $validator;
        $this->eavFilters = [];
    }

    /**
     * Filter by name
     *
     * @param string $value
     * @return void
     */
    public function name(string $value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        if ($operator === '=' || $operator === 'LIKE') {
            $operator = 'ILIKE';
            if (!Str::contains($value, '%')) {
                $value = "%{$value}%";
            }
        }
        
        $this->builder->where('name', $operator, $value);
    }

    /**
     * Filter by description
     *
     * @param string $value
     * @return void
     */
    public function description(string $value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        if ($operator === '=' || $operator === 'LIKE') {
            $operator = 'ILIKE';
            if (!Str::contains($value, '%')) {
                $value = "%{$value}%";
            }
        }
        
        $this->builder->where('description', $operator, $value);
    }

    /**
     * Filter by status
     *
     * @param string $value
     * @return void
     */
    public function status(string $value): void
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

    /**
     * Handle date filtering
     *
     * @param string $field
     * @param string $value
     * @return void
     */
    protected function handleDateFilter(string $field, string $value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        if (empty($value)) {
            return;
        }

        try {
            $date = Carbon::parse($value)->format('Y-m-d');
            
            // Ensure the operator is one of the supported ones
            if (!in_array($operator, ['=', '>', '<', '>=', '<='])) {
                $operator = '=';
            }
            
            $this->builder->whereDate($field, $operator, $date);

            Log::info('Date filter applied', [
                'field' => $field,
                'operator' => $operator,
                'value' => $date
            ]);
        } catch (\Exception $e) {
            Log::warning('Invalid date format', [
                'field' => $field,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Filter by creation date
     *
     * @param string $value
     * @return void
     */
    public function createdAt(string $value): void
    {
        $this->handleDateFilter('created_at', $value);
    }

    /**
     * Filter by update date
     *
     * @param string $value
     * @return void
     */
    public function updatedAt(string $value): void
    {
        $this->handleDateFilter('updated_at', $value);
    }

    /**
     * Add an EAV filter to the collection
     *
     * @param Attribute $attribute
     * @param string $operator
     * @param mixed $value
     * @return void
     */
    protected function addEavFilter(Attribute $attribute, string $operator, mixed $value): void
    {
        // Special handling for date attributes
        if ($attribute->type === 'date' && !empty($value)) {
            try {
                $value = Carbon::parse($value)->format('Y-m-d');
                Log::info('Parsed date value for EAV filter', [
                    'attribute' => $attribute->name,
                    'original' => $value,
                    'parsed' => $value
                ]);
            } catch (\Exception $e) {
                Log::warning('Invalid date format for EAV filter', [
                    'attribute' => $attribute->name,
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
                return;
            }
        }

        // Handle operator format
        if (in_array($operator, ['>', '<', '>=', '<=', '='])) {
            $this->eavFilters[] = [
                'attribute' => $attribute,
                'operator' => $operator,
                'value' => $value
            ];
        } else {
            $this->eavFilters[] = [
                'attribute' => $attribute,
                'operator' => '=',
                'value' => $value
            ];
        }
    }

    /**
     * Apply all collected EAV filters
     *
     * @return void
     */
    protected function applyEavFilters(): void
    {
        if (empty($this->eavFilters)) {
            return;
        }

        $this->builder->where(function ($query) {
            foreach ($this->eavFilters as $filter) {
                $query->whereHas('attributeValues', function ($subQuery) use ($filter) {
                    $subQuery->where('attribute_id', $filter['attribute']->id)
                            ->where('entity_type', 'App\\Models\\Project');

                    if ($filter['value'] === null) {
                        $subQuery->whereNull('value');
                    } else if ($filter['attribute']->type === 'select') {
                        $subQuery->whereRaw('LOWER(value) ' . $filter['operator'] . ' ?', [strtolower($filter['value'])]);
                    } else if ($filter['attribute']->type === 'date') {
                        // Handle date comparison with proper format and operator
                        $operator = $filter['operator'];
                        
                        if ($operator === 'LIKE') {
                            // Handle partial date match for year and month
                            $subQuery->whereRaw("DATE_FORMAT(value, '%Y-%m') LIKE ?", ['%' . $filter['value'] . '%']);
                        } else {
                            $date = Carbon::parse($filter['value'])->format('Y-m-d');
                            
                            // Ensure the operator is one of the supported ones
                            if (!in_array($operator, ['=', '>', '<', '>=', '<='])) {
                                $operator = '=';
                            }
                            
                            $subQuery->whereRaw("CAST(value AS DATE) {$operator} ?", [$date]);
                        }
                        
                        Log::info('Applied date filter', [
                            'attribute' => $filter['attribute']->name,
                            'operator' => $operator,
                            'value' => $filter['value'],
                            'sql' => $subQuery->toSql(),
                            'bindings' => $subQuery->getBindings()
                        ]);
                    } else {
                        $subQuery->where('value', $filter['operator'], $filter['value']);
                    }

                    Log::info('Subquery SQL', [
                        'sql' => $subQuery->toSql(),
                        'bindings' => $subQuery->getBindings()
                    ]);
                });
                Log::info('Main query SQL', [
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]);
            }
        });

        Log::info('Applied EAV filters', [
            'filters' => $this->eavFilters,
            'sql' => $this->builder->toSql(),
            'bindings' => $this->builder->getBindings()
        ]);
    }

    /**
     * Apply all filters to the query
     *
     * @param Builder $builder
     * @return Builder
     * @throws \App\Exceptions\FilterValidationException
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;
        $this->eavFilters = [];

        // Validate all filters before applying
        $this->validator->validate($this->filters());
        
        foreach ($this->filters() as $field => $value) {
            $method = Str::camel($field);
            
            // Handle regular model attributes
            if (method_exists($this, $method)) {
                call_user_func_array([$this, $method], [$value]);
                continue;
            }

            // Handle EAV attributes
            $attribute = Attribute::where('name', 'ILIKE', $field)->first();
            
            if ($attribute) {
                if ($value === null) {
                    $this->addEavFilter($attribute, '=', null);
                } else {
                    [$operator, $filterValue] = $this->parseOperator($value);
                    
                    // Handle date operators
                    if ($attribute->type === 'date' && in_array($operator, ['>', '<'])) {
                        try {
                            Carbon::parse($filterValue);
                        } catch (\Exception $e) {
                            $operator = '=';
                        }
                    }
                    
                    $this->addEavFilter($attribute, $operator, $filterValue);
                }
            }
        }

        // Apply all EAV filters together
        $this->applyEavFilters();

        Log::info('Final project filter query', [
            'sql' => $this->builder->toSql(),
            'bindings' => $this->builder->getBindings(),
            'filters' => $this->filters()
        ]);

        return $this->builder;
    }
} 