<?php

namespace App\Http\Requests;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Http\Requests\Concerns\NormalisesDomain;
use App\Models\Service;
use App\Rules\ValidDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared create/update validation for services (admin web and admin API).
 * A bound or scalar `service` route parameter marks an update.
 */
class ServiceRequest extends FormRequest
{
    use NormalisesDomain;

    public function authorize(): bool
    {
        return true;
    }

    /** The service being updated, or null on create. */
    protected function routeService(): ?Service
    {
        $param = $this->route('service');

        if ($param instanceof Service) {
            return $param;
        }

        return $param !== null && $param !== '' ? Service::find($param) : null;
    }

    protected function isUpdate(): bool
    {
        $param = $this->route('service');

        return $param !== null && $param !== '';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $update = $this->isUpdate();
        $ignoreId = $this->routeService()?->service_id;
        $presence = $update ? 'sometimes' : 'required';

        return [
            'company_id' => [$presence, 'integer', 'exists:customers,company_id'],
            'service_short' => [$presence, 'string', 'max:255'],
            'service_type' => [$presence, Rule::enum(ServiceType::class)],
            'status' => ['sometimes', Rule::enum(ServiceStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'service_monthly_charge' => ['nullable', 'numeric', 'min:0'],
            'service_payment_frequency' => ['nullable', 'string', 'max:50'],
            'domain_name' => [
                'nullable',
                'required_if:service_type,' . ServiceType::WebHosting->value,
                'string',
                'max:255',
                new ValidDomain,
                Rule::unique('services', 'domain_name')->ignore($ignoreId, 'service_id'),
            ],
            'cpanel_username' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[a-z][a-z0-9]*$/',
                Rule::unique('services', 'cpanel_username')->ignore($ignoreId, 'service_id'),
            ],

            // Web-only fields
            'provision_whm' => ['nullable', 'boolean'],
            'whm_package' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'required_with:stripe_price_id', 'email'],
            'stripe_price_id' => ['nullable', 'string'],

            // API-only fields
            'stripe_subscription_id' => ['nullable', 'string', 'max:255'],
            'next_payment_date' => ['nullable', 'date'],
        ];
    }
}
