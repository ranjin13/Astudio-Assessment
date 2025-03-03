<?php

namespace App\Filters;

use App\Exceptions\FilterValidationException;
use App\Models\Attribute;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FilterValidator
{
    protected array $allowedOperators = ['=', '>', '<', 'LIKE', 'ILIKE'];
    protected array $dateFields = ['start_date', 'end_date', 'date', 'created_at', 'updated_at'];
    protected array $textFields = ['name', 'description', 'status', 'task_name'];
    protected array $attributeFields = ['name', 'type'];
    protected array $timesheetFields = ['hours', 'task_name', 'date'];
    protected array $numericFields = ['hours'];

    /**
     * Validate filter parameters
     *
     * @param array $filters
     * @throws FilterValidationException
     */
    public function validate(array $filters): void
    {
        $errors = [];

        foreach ($filters as $field => $value) {
            try {
                $this->validateField($field, $value);
            } catch (\Exception $e) {
                $errors[$field] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new FilterValidationException($errors);
        }
    }

    /**
     * Validate a single filter field
     *
     * @param string $field
     * @param mixed $value
     * @throws \Exception
     */
    protected function validateField(string $field, mixed $value): void
    {
        // Validate operator if present
        if (is_string($value) && Str::contains($value, ':')) {
            [$operator] = explode(':', $value, 2);
            if (!in_array($operator, $this->allowedOperators)) {
                throw new \Exception("Invalid operator: {$operator}");
            }
        }

        // Validate numeric fields
        if (in_array($field, $this->numericFields)) {
            $this->validateNumericValue($value);
            return;
        }

        // Validate date fields
        if (in_array($field, $this->dateFields)) {
            $this->validateDateValue($value);
            return;
        }

        // Validate text fields
        if (in_array($field, $this->textFields) || in_array($field, $this->attributeFields)) {
            $this->validateTextValue($value);
            return;
        }

        // Validate EAV attributes
        $attribute = Attribute::where('name', 'ILIKE', $field)->first();
        if ($attribute) {
            $this->validateAttributeValue($attribute, $value);
            return;
        }

        throw new \Exception("Unknown filter field: {$field}");
    }

    /**
     * Validate numeric value
     *
     * @param mixed $value
     * @throws \Exception
     */
    protected function validateNumericValue(mixed $value): void
    {
        if (empty($value)) {
            return;
        }

        // Extract actual value if operator is present
        if (Str::contains($value, ':')) {
            [, $value] = explode(':', $value, 2);
        }

        if (!is_numeric($value)) {
            throw new \Exception('Value must be numeric');
        }
    }

    /**
     * Validate date value
     *
     * @param mixed $value
     * @throws \Exception
     */
    protected function validateDateValue(mixed $value): void
    {
        if (empty($value)) {
            return;
        }

        // Extract actual value if operator is present
        if (Str::contains($value, ':')) {
            [, $value] = explode(':', $value, 2);
        }

        try {
            Carbon::parse($value);
        } catch (\Exception $e) {
            throw new \Exception('Invalid date format');
        }
    }

    /**
     * Validate text value
     *
     * @param mixed $value
     * @throws \Exception
     */
    protected function validateTextValue(mixed $value): void
    {
        if (empty($value)) {
            return;
        }

        $validator = Validator::make(
            ['value' => $value],
            ['value' => 'string|max:255']
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first('value'));
        }
    }

    /**
     * Validate attribute value based on its type
     *
     * @param Attribute $attribute
     * @param mixed $value
     * @throws \Exception
     */
    protected function validateAttributeValue(Attribute $attribute, mixed $value): void
    {
        // Allow null values
        if ($value === null || $value === '') {
            return;
        }

        // Extract actual value if operator is present
        if (is_string($value) && Str::contains($value, ':')) {
            [, $value] = explode(':', $value, 2);
        }

        switch ($attribute->type) {
            case 'date':
                try {
                    Carbon::parse($value);
                } catch (\Exception $e) {
                    throw new \Exception('Invalid date format');
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    throw new \Exception('Value must be numeric');
                }
                break;

            case 'select':
                if ($value === null) {
                    return;
                }
                $options = array_map('strtolower', $attribute->options ?? []);
                if (!in_array(strtolower($value), $options)) {
                    throw new \Exception('Invalid option value');
                }
                break;

            default:
                if ($value !== null) {
                    $validator = Validator::make(
                        ['value' => $value],
                        ['value' => 'string|max:255']
                    );

                    if ($validator->fails()) {
                        throw new \Exception($validator->errors()->first('value'));
                    }
                }
                break;
        }
    }
} 