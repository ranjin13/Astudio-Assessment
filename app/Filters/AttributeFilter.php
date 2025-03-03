<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AttributeFilter extends QueryFilter
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
            // Only add wildcards if it's a LIKE operation and doesn't already have them
            if ($operator === 'ILIKE' && !Str::contains($value, '%')) {
                $value = "%{$value}%";
            }
        }
        
        Log::info('Applying name filter', [
            'operator' => $operator,
            'value' => $value
        ]);
        
        $this->builder->where('name', $operator, $value);
    }

    /**
     * Filter by type
     *
     * @param string $value
     * @return void
     */
    public function type(string $value): void
    {
        [$operator, $value] = $this->parseOperator($value);
        
        // For type, we want exact matches by default
        if ($operator === 'LIKE') {
            $operator = 'ILIKE';
            if (!Str::contains($value, '%')) {
                $value = "%{$value}%";
            }
        }
        
        Log::info('Applying type filter', [
            'operator' => $operator,
            'value' => $value
        ]);
        
        $this->builder->where('type', $operator, $value);
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

        Log::info('Final attribute filter query', [
            'sql' => $this->builder->toSql(),
            'bindings' => $this->builder->getBindings(),
            'filters' => $this->filters()
        ]);

        return $this->builder;
    }
} 