<?php

namespace App\Http\Requests;

use App\Models\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,completed,on-hold'],
            'attributes' => ['sometimes', 'array'],
            'attributes.*.attribute_id' => ['required_with:attributes', 'exists:attributes,id'],
            'attributes.*.value' => ['required_with:attributes', 'string'],
        ];

        // Add type-specific validation rules for attribute values
        if ($this->has('attributes')) {
            $startDateValue = null;
            $endDateValue = null;
            $startDateIndex = null;
            $endDateIndex = null;

            foreach ($this->input('attributes', []) as $index => $attribute) {
                if (!isset($attribute['attribute_id'])) {
                    continue;
                }

                $dbAttribute = Attribute::find($attribute['attribute_id']);
                if (!$dbAttribute) {
                    continue;
                }

                $valueRules = ['required', 'string'];

                switch ($dbAttribute->type) {
                    case 'date':
                        $valueRules[] = 'date';
                        
                        // Store date values for comparison
                        if ($dbAttribute->name === 'Start Date') {
                            $startDateValue = $attribute['value'] ?? null;
                            $startDateIndex = $index;
                        } elseif ($dbAttribute->name === 'End Date') {
                            $endDateValue = $attribute['value'] ?? null;
                            $endDateIndex = $index;
                        }
                        break;
                    case 'number':
                        $valueRules = ['required', 'numeric'];
                        break;
                    case 'select':
                        $valueRules[] = Rule::in($dbAttribute->options ?? []);
                        break;
                }

                $rules["attributes.{$index}.value"] = $valueRules;
            }

            // Add date comparison validation if both dates are present
            if ($startDateValue !== null && $endDateValue !== null) {
                $rules["attributes.{$endDateIndex}.value"][] = "after:{$startDateValue}";
            }
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            // For updates, we need to check against existing dates if only one is being updated
            if ($this->isUpdatingDates()) {
                $this->addDateComparisonRules($rules);
            }
            
            $rules = collect($rules)->mapWithKeys(function ($value, $key) {
                return [$key => array_merge(['sometimes'], $value)];
            })->toArray();
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The project name is required.',
            'name.string' => 'The project name must be a string.',
            'name.max' => 'The project name cannot exceed 255 characters.',
            'description.string' => 'The project description must be a string.',
            'status.required' => 'The project status is required.',
            'status.string' => 'The project status must be a string.',
            'status.in' => 'The project status must be one of: active, completed, on-hold.',
            'attributes.array' => 'Project attributes must be a list.',
            'attributes.*.attribute_id.required_with' => 'Each attribute must have an ID.',
            'attributes.*.attribute_id.exists' => 'One or more selected attributes do not exist.',
            'attributes.*.value.required_with' => 'Each attribute must have a value.',
            'attributes.*.value.string' => 'Attribute values must be strings.',
            'attributes.*.value.date' => 'The value must be a valid date.',
            'attributes.*.value.numeric' => 'The value must be a number.',
            'attributes.*.value.in' => 'The selected value is not a valid option.',
            'attributes.*.value.after' => 'The end date must be after the start date.',
        ];
    }

    /**
     * Add date comparison rules for updates
     */
    private function addDateComparisonRules(array &$rules): void
    {
        $id = $this->route('id');
        $project = \App\Models\Project::find($id);
        
        if (!$project) {
            return;
        }

        $startDateAttr = Attribute::where('name', 'Start Date')->first();
        $endDateAttr = Attribute::where('name', 'End Date')->first();
        
        if (!$startDateAttr || !$endDateAttr) {
            return;
        }

        // Get existing date values
        $existingStartDate = $project->attributeValues()
            ->where('attribute_id', $startDateAttr->id)
            ->value('value');
            
        $existingEndDate = $project->attributeValues()
            ->where('attribute_id', $endDateAttr->id)
            ->value('value');

        // Find new date values in request
        $newStartDate = null;
        $newEndDate = null;
        $endDateIndex = null;

        foreach ($this->input('attributes', []) as $index => $attribute) {
            $dbAttribute = Attribute::find($attribute['attribute_id'] ?? null);
            if (!$dbAttribute) continue;

            if ($dbAttribute->name === 'Start Date') {
                $newStartDate = $attribute['value'];
            } elseif ($dbAttribute->name === 'End Date') {
                $newEndDate = $attribute['value'];
                $endDateIndex = $index;
            }
        }

        // Add validation rules based on what's being updated
        if ($endDateIndex !== null) {
            $compareToDate = $newStartDate ?? $existingStartDate;
            if ($compareToDate) {
                $rules["attributes.{$endDateIndex}.value"][] = "after:{$compareToDate}";
            }
        }
    }

    /**
     * Check if we're updating date attributes
     */
    private function isUpdatingDates(): bool
    {
        $attributes = $this->input('attributes', []);
        $dateAttributes = ['Start Date', 'End Date'];
        
        foreach ($attributes as $attribute) {
            $dbAttribute = Attribute::find($attribute['attribute_id'] ?? null);
            if ($dbAttribute && in_array($dbAttribute->name, $dateAttributes)) {
                return true;
            }
        }
        
        return false;
    }
} 