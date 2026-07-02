<?php

namespace App\Http\Requests;

use App\Models\ProjectTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectTaskRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(ProjectTask::STATUSES)],
            'display_order' => ['sometimes', 'integer'],
        ];
    }
}
