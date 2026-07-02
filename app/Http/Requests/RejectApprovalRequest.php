<?php

namespace App\Http\Requests;

use App\Models\ProjectApprovalRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectApprovalRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $approval = $this->route('approval');

        $rules = ['reason' => ['nullable', 'string', 'max:1000']];

        // For Project Completion type, reason is required (between 1 and 1000 chars)
        if ($approval && $approval->type === 'Project Completion') {
            $rules['reason'] = ['required', 'string', 'min:1', 'max:1000'];
        }

        return $rules;
    }
}
