@php
    $serviceOptions = [
        'Web Design / Development',
        'Technology Advice or Installation',
        'Event Ticketing Purchasing',
        'Custom Software Development',
        'Construction Site Management Software',
        'Drone Hire',
        'Other',
    ];

    $orgTypeOptions = [
        'Business',
        'Charity',
        'CIC',
        'Community organisation',
        'Other',
    ];

    $heardAboutOptions = [
        'Google / Search',
        'Social media',
        'Referral / Word of mouth',
        'Existing customer',
        'Event',
        'Other',
    ];

    $privacyUrl = 'https://ckenterprises.co.uk/privacy';
@endphp

<x-embed-layout>
    @if (session('submitted'))
        {{-- Success state --}}
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-slate-900">Thanks, we've got it</h2>
            <p class="mt-2 text-sm text-slate-600">
                We've sent a confirmation to your inbox and a member of our team will be in touch soon.
            </p>
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">How can we help?</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Tell us a little about your organisation and what you're looking to achieve. We'll get back to you.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Please check the highlighted fields below and try again.
                </div>
            @endif

            <form method="POST" action="{{ route('signup.store') }}" class="space-y-5" x-data="{
                orgType: '{{ old('organisation_type') }}',
                services: {{ \Illuminate\Support\Js::from(old('services', [])) }},
                get otherService() { return this.services.includes('Other'); }
            }">
                @csrf

                {{-- Honeypot: hidden from humans, bots tend to fill it --}}
                <div class="hidden" aria-hidden="true">
                    <label>Leave this field empty
                        <input type="text" name="company_url" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Your name <span class="text-red-500">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('name') border-red-400 @enderror">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Organisation --}}
                <div>
                    <label for="organisation" class="block text-sm font-medium text-slate-700">Organisation / business name <span class="text-red-500">*</span></label>
                    <input id="organisation" name="organisation" type="text" value="{{ old('organisation') }}" required
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('organisation') border-red-400 @enderror">
                    @error('organisation')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Email + Phone --}}
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700">Email address <span class="text-red-500">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-400 @enderror">
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700">Telephone number</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Website --}}
                <div>
                    <label for="website" class="block text-sm font-medium text-slate-700">Website</label>
                    <input id="website" name="website" type="url" value="{{ old('website') }}" placeholder="https://"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('website')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Organisation type --}}
                <div>
                    <span class="block text-sm font-medium text-slate-700">What type of organisation are you? <span class="text-red-500">*</span></span>
                    <div class="mt-2 space-y-2">
                        @foreach ($orgTypeOptions as $option)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="radio" name="organisation_type" value="{{ $option }}" x-model="orgType"
                                    @checked(old('organisation_type') === $option)
                                    class="border-slate-300 text-blue-600 focus:ring-blue-500">
                                {{ $option }}
                            </label>
                        @endforeach
                    </div>
                    <div x-show="orgType === 'Other'" x-cloak class="mt-2">
                        <input name="organisation_type_other" type="text" value="{{ old('organisation_type_other') }}" placeholder="Please specify"
                            class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    @error('organisation_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('organisation_type_other')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Services --}}
                <div>
                    <span class="block text-sm font-medium text-slate-700">What can we help you with? <span class="text-red-500">*</span></span>
                    <p class="text-xs text-slate-500">Select all that apply.</p>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($serviceOptions as $option)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="services[]" value="{{ $option }}" x-model="services"
                                    @checked(in_array($option, old('services', [])))
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                {{ $option }}
                            </label>
                        @endforeach
                    </div>
                    <div x-show="otherService" x-cloak class="mt-2">
                        <input name="services_other" type="text" value="{{ old('services_other') }}" placeholder="Please specify"
                            class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    @error('services')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('services_other')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Message --}}
                <div>
                    <label for="message" class="block text-sm font-medium text-slate-700">Tell us briefly what you're looking to achieve <span class="text-red-500">*</span></label>
                    <textarea id="message" name="message" rows="4" required
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('message') border-red-400 @enderror">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Heard about + Preferred contact --}}
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="heard_about" class="block text-sm font-medium text-slate-700">How did you hear about us?</label>
                        <select id="heard_about" name="heard_about"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Please choose…</option>
                            @foreach ($heardAboutOptions as $option)
                                <option value="{{ $option }}" @selected(old('heard_about') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-slate-700">Preferred way to contact you</span>
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach (['Email', 'Phone', 'Either'] as $option)
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="radio" name="preferred_contact" value="{{ $option }}"
                                        @checked(old('preferred_contact') === $option)
                                        class="border-slate-300 text-blue-600 focus:ring-blue-500">
                                    {{ $option }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Privacy --}}
                <div>
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))
                            class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span>
                            I agree that CK Enterprises can contact me about this enquiry. See our
                            <a href="{{ $privacyUrl }}" target="_blank" rel="noopener" class="text-blue-600 underline">Privacy Policy</a>.
                            <span class="text-red-500">*</span>
                        </span>
                    </label>
                    @error('privacy')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto">
                        Send enquiry
                    </button>
                </div>
            </form>
        </div>
    @endif
</x-embed-layout>
