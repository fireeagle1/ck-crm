<?php

namespace App\Http\Requests;

use App\Models\ProjectDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectDocumentRequest extends FormRequest
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
        return [
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,docx,xlsx,png,jpg,zip'],
            'label' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', Rule::in(ProjectDocument::TYPES)],
        ];
    }
}
