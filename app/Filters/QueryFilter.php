<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class QueryFilter
{
    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @var Builder
     */
    protected Builder $builder;

    /**
     * @var array
     */
    protected array $operators = ['=', '>', '<', 'LIKE', 'ILIKE'];

    /**
     * Constructor
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply filters to the query builder
     *
     * @param Builder $builder
     * @return Builder
     */
    abstract public function apply(Builder $builder): Builder;

    /**
     * Get all filters from the request
     *
     * @return array
     */
    protected function filters(): array
    {
        return $this->request->get('filters', []);
    }

    /**
     * Parse operator and value from filter string
     *
     * @param string $value
     * @return array
     */
    protected function parseOperator(string $value): array
    {
        $operator = '=';
        
        // Check for operator at start of value (e.g., "LIKE:Project")
        foreach ($this->operators as $op) {
            if (Str::startsWith($value, $op . ':')) {
                $operator = $op;
                $value = trim(substr($value, strlen($op) + 1)); // +1 for the colon
                break;
            }
        }

        // Handle LIKE operator wildcards
        if ($operator === 'LIKE' && !Str::contains($value, '%')) {
            $value = "%{$value}%";
        }

        return [$operator, $value];
    }
} 