<x-guest-layout>
    <h2 class="text-xl font-semibold text-gray-900">Register your company</h2>
    <p class="text-sm text-gray-500 mt-1 mb-6">Create an account for your business to access our services and shop.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('register') }}" class="space-y-6" id="registration-form">
        @csrf

        {{-- Honeypot fields — hidden from real users, bots will fill them --}}
        <div style="position: absolute; left: -9999px; top: -9999px;" aria-hidden="true" tabindex="-1">
            <label for="website_url">Website URL</label>
            <input type="text" name="website_url" id="website_url" value="" autocomplete="off" tabindex="-1">
            <label for="fax_number">Fax Number</label>
            <input type="text" name="fax_number" id="fax_number" value="" autocomplete="off" tabindex="-1">
        </div>
        {{-- Timestamp token — reject if submitted too quickly --}}
        <input type="hidden" name="_form_token" value="{{ base64_encode(time()) }}">

        {{-- ─── Section: Company Details ─── --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-gray-900 uppercase tracking-wide border-b pb-2 w-full">Company Details</legend>

            <div>
                <label for="company_name" class="block text-sm font-medium text-gray-700">Company name <span class="text-red-500">*</span></label>
                <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
            </div>

            <div>
                <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone number</label>
                <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
            </div>

            <div>
                <label for="address_line1" class="block text-sm font-medium text-gray-700">Address line 1 <span class="text-red-500">*</span></label>
                <input id="address_line1" type="text" name="address_line1" value="{{ old('address_line1') }}" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <x-input-error :messages="$errors->get('address_line1')" class="mt-2" />
            </div>

            <div>
                <label for="address_line2" class="block text-sm font-medium text-gray-700">Address line 2</label>
                <input id="address_line2" type="text" name="address_line2" value="{{ old('address_line2') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <x-input-error :messages="$errors->get('address_line2')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">City <span class="text-red-500">*</span></label>
                    <input id="city" type="text" name="city" value="{{ old('city') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700">County / State</label>
                    <input id="state" type="text" name="state" value="{{ old('state') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <x-input-error :messages="$errors->get('state')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="postal_code" class="block text-sm font-medium text-gray-700">Postcode <span class="text-red-500">*</span></label>
                    <input id="postal_code" type="text" name="postal_code" value="{{ old('postal_code') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                </div>
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                    <input id="country" type="text" name="country" value="{{ old('country', 'United Kingdom') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <x-input-error :messages="$errors->get('country')" class="mt-2" />
                </div>
            </div>
        </fieldset>

        {{-- ─── Section: Primary User (Your Account) ─── --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-gray-900 uppercase tracking-wide border-b pb-2 w-full">Your Account</legend>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First name <span class="text-red-500">*</span></label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last name <span class="text-red-500">*</span></label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address <span class="text-red-500">*</span></label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password <span class="text-red-500">*</span></label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </fieldset>

        {{-- ─── Section: Additional Users (Optional) ─── --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-gray-900 uppercase tracking-wide border-b pb-2 w-full">Additional Users <span class="text-xs font-normal normal-case text-gray-500">(optional)</span></legend>
            <p class="text-sm text-gray-500">Add team members who need access. They'll receive a password reset email to set up their account.</p>

            <div id="additional-users-container">
                {{-- Existing old input --}}
                @if (old('additional_users'))
                    @foreach (old('additional_users') as $index => $user)
                        <div class="additional-user-row border rounded-md p-4 space-y-3 bg-gray-50 mb-3" data-index="{{ $index }}">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700">User {{ $index + 1 }}</span>
                                <button type="button" onclick="this.closest('.additional-user-row').remove()" class="text-red-500 hover:text-red-700 text-sm">Remove</button>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <input type="text" name="additional_users[{{ $index }}][first_name]" value="{{ $user['first_name'] ?? '' }}" placeholder="First name"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <x-input-error :messages="$errors->get('additional_users.' . $index . '.first_name')" class="mt-1" />
                                </div>
                                <div>
                                    <input type="text" name="additional_users[{{ $index }}][last_name]" value="{{ $user['last_name'] ?? '' }}" placeholder="Last name"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <x-input-error :messages="$errors->get('additional_users.' . $index . '.last_name')" class="mt-1" />
                                </div>
                            </div>
                            <div>
                                <input type="email" name="additional_users[{{ $index }}][email]" value="{{ $user['email'] ?? '' }}" placeholder="Email address"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <x-input-error :messages="$errors->get('additional_users.' . $index . '.email')" class="mt-1" />
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <button type="button" id="add-user-btn"
                    class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add another user
            </button>
        </fieldset>

        {{-- ─── Section: Terms & Conditions ─── --}}
        <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-gray-900 uppercase tracking-wide border-b pb-2 w-full">Terms & Conditions</legend>

            <div class="bg-gray-50 border rounded-md p-4 max-h-48 overflow-y-auto text-sm text-gray-700 space-y-3">
                @php $termsText = \App\Models\Setting::get('terms_conditions'); @endphp
                @if ($termsText)
                    {!! nl2br(e($termsText)) !!}
                @else
                    <p><strong>1. Service Agreement</strong><br>By registering an account, you agree to use our services in accordance with the terms set out below and any additional service-specific terms presented at the time of purchase.</p>
                    <p><strong>2. Account Responsibility</strong><br>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You must notify us immediately of any unauthorised use.</p>
                    <p><strong>3. Payment Terms</strong><br>Services purchased through our platform are subject to the pricing displayed at the time of purchase. Recurring services will be billed according to the billing cycle selected. Late payments may result in service suspension.</p>
                    <p><strong>4. Acceptable Use</strong><br>You agree not to use our services for any unlawful purpose, to distribute malware, send unsolicited communications, or otherwise breach applicable laws and regulations.</p>
                    <p><strong>5. Data Protection</strong><br>We process your personal data in accordance with our Privacy Policy and applicable data protection legislation including the UK GDPR. Your data will not be shared with third parties except as necessary to deliver our services.</p>
                    <p><strong>6. Service Level</strong><br>We aim to maintain high availability of all services but do not guarantee uninterrupted access. Scheduled maintenance windows will be communicated in advance where possible.</p>
                    <p><strong>7. Termination</strong><br>Either party may terminate this agreement with 30 days written notice. Upon termination, you remain liable for any outstanding charges. We reserve the right to suspend services immediately for breach of these terms.</p>
                    <p><strong>8. Limitation of Liability</strong><br>Our liability is limited to the fees paid for the services in question during the 12 months preceding any claim. We are not liable for indirect, consequential, or incidental damages.</p>
                @endif
            </div>

            <div class="flex items-start gap-2">
                <input id="terms_accepted" type="checkbox" name="terms_accepted" value="1"
                       {{ old('terms_accepted') ? 'checked' : '' }}
                       class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                <label for="terms_accepted" class="text-sm text-gray-700">
                    I have read and agree to the terms and conditions above. <span class="text-red-500">*</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('terms_accepted')" class="mt-1" />
        </fieldset>

        {{-- Submit --}}
        <button type="submit" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
            Create account &amp; browse services
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Already have an account?
        <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-700 font-medium">Sign in</a>
    </p>

    {{-- JavaScript for adding additional users dynamically --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('additional-users-container');
            const addBtn = document.getElementById('add-user-btn');
            let userIndex = container.querySelectorAll('.additional-user-row').length;

            addBtn.addEventListener('click', function () {
                if (userIndex >= 5) {
                    alert('You can add a maximum of 5 additional users during registration. More can be added later from your account settings.');
                    return;
                }

                const row = document.createElement('div');
                row.className = 'additional-user-row border rounded-md p-4 space-y-3 bg-gray-50 mb-3';
                row.dataset.index = userIndex;
                row.innerHTML = `
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">User ${userIndex + 1}</span>
                        <button type="button" onclick="this.closest('.additional-user-row').remove()" class="text-red-500 hover:text-red-700 text-sm">Remove</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <input type="text" name="additional_users[${userIndex}][first_name]" placeholder="First name" required
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                        <div>
                            <input type="text" name="additional_users[${userIndex}][last_name]" placeholder="Last name" required
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <input type="email" name="additional_users[${userIndex}][email]" placeholder="Email address" required
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                `;

                container.appendChild(row);
                userIndex++;
            });
        });
    </script>
</x-guest-layout>
