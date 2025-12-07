<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:ACTIVE,ON_HOLD,COMPLETED,CANCELLED',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
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
            'name.required' => 'Project name is required',
            'name.max' => 'Project name cannot exceed 255 characters',
            'status.in' => 'Status must be one of: ACTIVE, ON_HOLD, COMPLETED, CANCELLED',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}
