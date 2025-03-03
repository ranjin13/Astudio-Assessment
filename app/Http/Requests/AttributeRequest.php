<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttributeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:attributes,name'],
            'type' => ['required', Rule::in(['text', 'number', 'date', 'select'])],
            'options' => ['required_if:type,select', 'array', 'min:1'],
            'options.*' => ['required_if:type,select', 'string', 'max:255', 'distinct'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = collect($rules)->mapWithKeys(function ($value, $key) {
                $value = $key === 'name' 
                    ? array_merge(['sometimes'], array_map(function ($rule) {
                        return $rule === 'unique:attributes,name' 
                            ? 'unique:attributes,name,' . $this->attribute->id 
                            : $rule;
                    }, $value))
                    : array_merge(['sometimes'], $value);
                
                return [$key => $value];
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
            'name.required' => 'The attribute name is required.',
            'name.string' => 'The attribute name must be a string.',
            'name.max' => 'The attribute name cannot exceed 255 characters.',
            'name.unique' => 'This attribute name is already in use.',
            'type.required' => 'The attribute type is required.',
            'type.in' => 'The attribute type must be one of: text, number, date, select.',
            'options.required_if' => 'Options are required when type is select.',
            'options.array' => 'Options must be a list of values.',
            'options.min' => 'At least one option is required for select type.',
            'options.*.required_if' => 'Each option value is required when type is select.',
            'options.*.string' => 'Each option value must be a string.',
            'options.*.max' => 'Each option value cannot exceed 255 characters.',
            'options.*.distinct' => 'All options must be unique.',
        ];
    }
} 