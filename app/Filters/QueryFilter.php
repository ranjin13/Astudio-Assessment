<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class QueryFilter
{
    protected Request $request;
    protected Builder $builder;
    protected array $operators = ['=', '>', '<', 'LIKE'];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->filters() as $field => $value) {
            $method = Str::camel($field);
            if (method_exists($this, $method)) {
                call_user_func_array([$this, $method], [$value]);
            }
        }

        return $this->builder;
    }

    protected function filters(): array
    {
        return $this->request->get('filters', []);
    }

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