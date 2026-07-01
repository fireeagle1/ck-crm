<x-admin-layout>
    <x-slot:title>Communications</x-slot:title>

    <h1 class="text-2xl font-semibold mb-2">Send Email</h1>
    <p class="text-sm text-gray-500 mb-6">Send branded emails to all customers or selected ones.</p>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.communications.send') }}" x-data="{ recipients: 'all' }">
            @csrf

            <div class="space-y-5">
                {{-- Recipients --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Recipients</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="recipients" value="all" x-model="recipients"
                                   class="text-blue-600 focus:ring-blue-500">
                            All customers
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="recipients" value="selected" x-model="recipients"
                                   class="text-blue-600 focus:ring-blue-500">
                            Selected customers
                        </label>
                    </div>
                </div>

                {{-- Customer selector (shown when "selected" is chosen) --}}
                <div x-show="recipients === 'selected'" x-transition>
                    <label for="customer_ids" class="block text-sm font-medium text-gray-700 mb-1">Select Customers</label>
                    <select name="customer_ids[]" id="customer_ids" multiple
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            size="8">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->company_id }}">{{ $customer->company_name ?: $customer->customer_name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple.</p>
                </div>

                {{-- Subject --}}
                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                    <input type="text" name="subject" id="subject" required value="{{ old('subject') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g. Important update about your services">
                    @error('subject') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Body --}}
                <div>
                    <label for="body" class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea name="body" id="body" rows="10" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Write your message here. It will be wrapped in the branded email template with header, footer, and action buttons.">{{ old('body') }}</textarea>
                    @error('body') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 mt-1">The email will include your logo, the customer's first name as greeting, and footer links to the portal/knowledgebase.</p>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-md p-3 text-sm text-amber-800">
                    <strong>Note:</strong> Emails are sent immediately. Each recipient will receive an individual email addressed to them personally.
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
                        onclick="return confirm('Send this email? This cannot be undone.')">
                    Send Email
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
