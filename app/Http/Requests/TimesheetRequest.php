<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\User;

class TimesheetRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'task_name' => ['required', 'string', 'max:255'],
            'project_id' => [
                'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    /** @var User $user */
                    $user = Auth::user();
                    if (!$user->projects()->where('projects.id', $value)->exists()) {
                        $fail('You can only create timesheets for projects you are assigned to.');
                    }
                }
            ],
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value !== Auth::id()) {
                        $fail('You can only create timesheets for yourself.');
                    }
                }
            ],
        ];

        // Add conditional rules for update requests
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
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
            'date.required' => 'The date field is required.',
            'date.date' => 'The date must be a valid date.',
            'hours.required' => 'The hours field is required.',
            'hours.numeric' => 'The hours must be a number.',
            'hours.min' => 'The hours must be at least 0.',
            'hours.max' => 'The hours cannot exceed 24.',
            'task_name.required' => 'The task name field is required.',
            'task_name.string' => 'The task name must be a string.',
            'task_name.max' => 'The task name cannot exceed 255 characters.',
            'project_id.required' => 'The project ID is required.',
            'project_id.exists' => 'The selected project does not exist.',
            'user_id.required' => 'The user ID is required.',
            'user_id.exists' => 'The selected user does not exist.',
        ];
    }
} 