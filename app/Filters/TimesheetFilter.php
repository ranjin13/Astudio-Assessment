<?php

namespace App\Filters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TimesheetFilter extends QueryFilter
{
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
    }

    /**
     * Filter by hours
     *
     * @param string $value
     * @return void
     */
    public function hours(string $value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        if (!is_numeric($value)) {
            return;
        }

        Log::info('Applying hours filter', [
            'operator' => $operator,
            'value' => $value
        ]);

        $this->builder->where('hours', $operator, (float) $value);
    }

    /**
     * Filter by task name
     *
     * @param string $value
     * @return void
     */
    public function taskName(string $value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        if ($operator === '=' || $operator === 'LIKE') {
            $operator = 'ILIKE';
            if (!Str::contains($value, '%')) {
                $value = "%{$value}%";
            }
        }
        
        Log::info('Applying task_name filter', [
            'operator' => $operator,
            'value' => $value
        ]);
        
        $this->builder->where('task_name', $operator, $value);
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
            $date = Carbon::parse($value)->toDateString();
            
            Log::info('Applying date filter', [
                'field' => $field,
                'operator' => $operator,
                'value' => $date
            ]);

            $this->builder->whereDate($field, $operator, $date);
        } catch (\Exception $e) {
            Log::warning('Invalid date format', [
                'field' => $field,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Filter by date
     *
     * @param string $value
     * @return void
     */
    public function date(string $value): void
    {
        $this->handleDateFilter('date', $value);
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
     * Apply all filters to the query
     *
     * @param Builder $builder
     * @return Builder
     * @throws \App\Exceptions\FilterValidationException
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        // Validate all filters before applying
        $this->validator->validate($this->filters());
        
        foreach ($this->filters() as $field => $value) {
            $method = Str::camel($field);
            
            if (method_exists($this, $method)) {
                call_user_func_array([$this, $method], [$value]);
            }
        }

        Log::info('Final timesheet filter query', [
            'sql' => $this->builder->toSql(),
            'bindings' => $this->builder->getBindings(),
            'filters' => $this->filters()
        ]);

        return $this->builder;
    }
} 