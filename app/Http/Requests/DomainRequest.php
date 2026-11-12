<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalisesDomain;
use App\Models\Domain;
use App\Rules\ValidDomain;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin create/update validation for domains. domain_name is normalised before
 * validation; uniqueness includes soft-deleted rows so a trashed duplicate is
 * reported with its id (flashed as `trashed_domain_id`) for a restore link.
 */
class DomainRequest extends FormRequest
{
    use NormalisesDomain;

    private ?int $trashedDomainId = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $current = $this->route('domain');
        $ignoreId = $current instanceof Domain ? $current->getKey() : $current;
        $update = $ignoreId !== null;

        return [
            'domain_name' => [
                'required',
                'string',
                'max:255',
                new ValidDomain,
                function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
                    $existing = Domain::withTrashed()
                        ->where('domain_name', $value)
                        ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                        ->first();

                    if ($existing === null) {
                        return;
                    }

                    if ($existing->trashed()) {
                        $this->trashedDomainId = $existing->id;
                        $fail("This domain was previously deleted (trashed domain #{$existing->id}). Restore it instead of adding a duplicate.");

                        return;
                    }

                    $fail('The domain name has already been taken.');
                },
            ],
            'company_id' => 'nullable|exists:customers,company_id',
            'registrar' => 'nullable|string|max:255',
            'registration_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
            'domain_admin_notes' => 'nullable|string',
        ] + ($update ? ['stripe_subscription_id' => 'nullable|string|max:255'] : []);
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->trashedDomainId !== null && $this->hasSession()) {
            $this->session()->flash('trashed_domain_id', $this->trashedDomainId);
        }

        parent::failedValidation($validator);
    }
}
