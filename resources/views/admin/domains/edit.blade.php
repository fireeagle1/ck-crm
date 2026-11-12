<x-admin-layout>
    <x-slot:title>Edit {{ $domain->domain_name }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Edit Domain</h1>
        <a href="{{ route('admin.domains.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Domains</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl mb-4" id="domain-sync-panel">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">eNom sync</h2>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd class="mt-1"><x-status-badge :status="$domain->status" type="domain-sync" /></dd>
            </div>
            <div>
                <dt class="text-gray-500">Last synced</dt>
                <dd class="mt-1">{{ $domain->last_synced_at?->format('d M Y H:i') ?? 'Never' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Linked service</dt>
                <dd class="mt-1">
                    @forelse ($domain->services as $linkedService)
                        <a href="{{ route('admin.services.show', $linkedService) }}" class="text-blue-600 hover:underline">{{ $linkedService->service_short }}</a>@if (! $loop->last), @endif
                    @empty
                        <span class="text-gray-500">None</span>
                    @endforelse
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Admin-edited fields</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    @forelse ($domain->admin_edited_fields ?? [] as $editedField)
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-xs">Edited: {{ str_replace('_', ' ', $editedField) }}</span>
                    @empty
                        <span class="text-gray-500">None</span>
                    @endforelse
                </dd>
            </div>
        </dl>
        @if (! empty($domain->admin_edited_fields))
            <form method="POST" action="{{ route('admin.domains.reset-edits', $domain) }}" class="mt-4">
                @csrf
                <button type="submit" class="px-3 py-1.5 border rounded-md text-sm font-medium hover:bg-gray-50">Reset to eNom control</button>
            </form>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl mb-4" id="domain-link-service-panel">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Link service</h2>
        <form method="POST" action="{{ route('admin.domains.link-service', $domain) }}" class="flex items-end gap-3">
            @csrf
            <div>
                <label for="service_id" class="block text-sm font-medium text-gray-700">Service ID</label>
                <input type="number" min="1" name="service_id" id="service_id" required
                       value="{{ old('service_id') }}"
                       class="mt-1 block rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                @error('service_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="px-3 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">Link service</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.domains.update', $domain) }}">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label for="domain_name" class="block text-sm font-medium text-gray-700">Domain Name <span class="text-red-500">*</span></label>
                    <input type="text" name="domain_name" id="domain_name" required
                           value="{{ old('domain_name', $domain->domain_name) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('domain_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    @if (session('trashed_domain_id'))
                        <button type="submit" form="restore-domain-form" class="mt-2 px-3 py-1.5 border rounded-md text-sm font-medium hover:bg-gray-50">Restore deleted domain</button>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-customer-picker :selected="$selectedCustomer" />
                    </div>
                    <div>
                        <label for="registrar" class="block text-sm font-medium text-gray-700">Registrar</label>
                        <input type="text" name="registrar" id="registrar"
                               value="{{ old('registrar', $domain->registrar) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="registration_date" class="block text-sm font-medium text-gray-700">Registered</label>
                        <input type="date" name="registration_date" id="registration_date"
                               value="{{ old('registration_date', $domain->registration_date?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expires</label>
                        <input type="date" name="expiry_date" id="expiry_date"
                               value="{{ old('expiry_date', $domain->expiry_date?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="cost" class="block text-sm font-medium text-gray-700">Annual Cost (£)</label>
                        @if ($domain->stripe_subscription_id)
                            <input type="number" step="0.01" min="0" id="cost"
                                   value="{{ $domain->cost }}" readonly disabled
                                   class="mt-1 block w-full rounded-md border-gray-200 bg-gray-100 text-gray-500 shadow-sm cursor-not-allowed">
                            <p class="text-xs text-amber-600 mt-1">🔒 Pricing synced from Stripe</p>
                        @else
                            <input type="number" step="0.01" min="0" name="cost" id="cost"
                                   value="{{ old('cost', $domain->cost) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @endif
                    </div>
                </div>

                <div>
                    <label for="domain_admin_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="domain_admin_notes" id="domain_admin_notes" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('domain_admin_notes', $domain->domain_admin_notes) }}</textarea>
                </div>

                <div>
                    <label for="stripe_subscription_id" class="block text-sm font-medium text-gray-700">Stripe Subscription ID</label>
                    <input type="text" name="stripe_subscription_id" id="stripe_subscription_id"
                           value="{{ old('stripe_subscription_id', $domain->stripe_subscription_id) }}"
                           placeholder="sub_..."
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 font-mono text-sm">
                    <p class="text-xs text-gray-400 mt-1">If domain renewal is billed via a Stripe subscription, enter the ID here.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Save Changes
                    </button>
                    <a href="{{ route('admin.domains.index') }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    @if (session('trashed_domain_id'))
        <form id="restore-domain-form" method="POST" action="{{ route('admin.domains.restore', session('trashed_domain_id')) }}">
            @csrf
        </form>
    @endif
</x-admin-layout>
