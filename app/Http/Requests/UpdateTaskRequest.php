<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => 'sometimes|required|exists:projects,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:TODO,IN_PROGRESS,DONE',
            'priority' => 'nullable|in:LOW,MEDIUM,HIGH',
            'due_date' => 'nullable|date',
            'assignee_id' => 'nullable|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Project ID is required',
            'project_id.exists' => 'The selected project does not exist',
            'name.required' => 'Task name is required',
            'name.max' => 'Task name cannot exceed 255 characters',
            'status.in' => 'Status must be one of: TODO, IN_PROGRESS, DONE',
            'priority.in' => 'Priority must be one of: LOW, MEDIUM, HIGH',
            'assignee_id.exists' => 'The selected assignee does not exist',
        ];
    }
}
