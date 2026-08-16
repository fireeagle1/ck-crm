<x-portal-layout>
    <x-slot:title>Checkout</x-slot:title>

    <h1 class="text-3xl font-bold tracking-tight mb-2">Checkout</h1>
    <p class="text-gray-500 mb-6">Review your items and complete your order.</p>

    <form method="POST" action="{{ route('portal.cart.checkout') }}" id="checkout-form">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left column: Main checkout sections --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Order Summary --}}
                <div class="bg-white rounded-lg border overflow-hidden">
                    <div class="px-5 py-4 border-b bg-gray-50">
                        <h2 class="font-semibold text-gray-900">Order Summary</h2>
                    </div>
                    <div class="divide-y">
                        @foreach ($items as $index => $item)
                            <div class="px-5 py-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900">{{ $item['name'] }}</p>
                                        <div class="mt-1 flex flex-wrap gap-2 text-sm text-gray-500">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                @switch($item['product_type'])
                                                    @case('hosting') bg-blue-100 text-blue-700 @break
                                                    @case('equipment_rental') bg-amber-100 text-amber-700 @break
                                                    @case('one_off') bg-green-100 text-green-700 @break
                                                @endswitch
                                            ">
                                                {{ str_replace('_', ' ', ucfirst($item['product_type'])) }}
                                            </span>

                                            @if ($item['product_type'] === 'equipment_rental' && !empty($item['rental_start_date']) && !empty($item['rental_end_date']))
                                                <span class="text-gray-500">
                                                    {{ \Carbon\Carbon::parse($item['rental_start_date'])->format('d M Y') }}
                                                    &ndash;
                                                    {{ \Carbon\Carbon::parse($item['rental_end_date'])->format('d M Y') }}
                                                </span>
                                            @endif

                                            @if ($item['product_type'] === 'hosting' && !empty($item['domain_name']))
                                                <span class="text-gray-500">
                                                    Domain: <strong>{{ $item['domain_name'] }}</strong>
                                                </span>
                                            @endif

                                            @if (($item['quantity'] ?? 1) > 1)
                                                <span class="text-gray-500">Qty: {{ $item['quantity'] }}</span>
                                            @endif
                                        </div>

                                        @if ($item['product_type'] === 'equipment_rental' && !empty($item['rental_start_date']) && !empty($item['rental_end_date']))
                                            @php
                                                $days = \Carbon\Carbon::parse($item['rental_start_date'])->diffInDays(\Carbon\Carbon::parse($item['rental_end_date']));
                                            @endphp
                                            <p class="text-xs text-gray-400 mt-1">
                                                {{ $days }} {{ Str::plural('day', $days) }} &times; &pound;{{ number_format($item['price'], 2) }}/day
                                                @if (($item['quantity'] ?? 1) > 1)
                                                    &times; {{ $item['quantity'] }} units
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                    <div class="text-right ml-4">
                                        <p class="font-semibold text-gray-900">&pound;{{ number_format($item['total_price'] ?? $item['price'], 2) }}</p>
                                        @if ($item['billing_frequency'])
                                            <p class="text-xs text-gray-500">/{{ $item['billing_frequency'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Delivery Method (shown only for physical items) --}}
                @if ($hasPhysicalItems)
                    <div class="bg-white rounded-lg border overflow-hidden" x-data="{ deliveryMethod: '{{ old('delivery_method', 'delivery') }}' }">
                        <div class="px-5 py-4 border-b bg-gray-50">
                            <h2 class="font-semibold text-gray-900">Delivery Method</h2>
                            <p class="text-sm text-gray-500 mt-1">Choose how you'd like to receive your items.</p>
                        </div>
                        <div class="px-5 py-4 space-y-3">
                            <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition"
                                   :class="deliveryMethod === 'delivery' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                <input type="radio" name="delivery_method" value="delivery"
                                       x-model="deliveryMethod"
                                       class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <p class="font-medium text-gray-900">Delivery</p>
                                    <p class="text-sm text-gray-500">We'll deliver to your address.
                                        @if ($deliveryTotal > 0)
                                            <span class="font-semibold text-gray-700">&pound;{{ number_format($deliveryTotal, 2) }}</span>
                                        @else
                                            <span class="text-green-600 font-medium">Free</span>
                                        @endif
                                    </p>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition"
                                   :class="deliveryMethod === 'collection' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                <input type="radio" name="delivery_method" value="collection"
                                       x-model="deliveryMethod"
                                       class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <p class="font-medium text-gray-900">Collection</p>
                                    <p class="text-sm text-gray-500">Collect from North Manchester. <span class="text-green-600 font-medium">Free</span></p>
                                </div>
                            </label>
                        </div>

                        {{-- Delivery Address (shown only when delivery is selected) --}}
                        <div x-show="deliveryMethod === 'delivery'" x-transition class="px-5 py-4 border-t space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Delivery Address</h3>
                            <div>
                                <label for="address_line1" class="block text-sm font-medium text-gray-700">Address Line 1 <span class="text-red-500">*</span></label>
                                <input type="text" name="address_line1" id="address_line1"
                                       value="{{ old('address_line1', $customer->address_line1) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                       :required="deliveryMethod === 'delivery'">
                                @error('address_line1')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="address_line2" class="block text-sm font-medium text-gray-700">Address Line 2</label>
                                <input type="text" name="address_line2" id="address_line2"
                                       value="{{ old('address_line2', $customer->address_line2) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700">City <span class="text-red-500">*</span></label>
                                    <input type="text" name="city" id="city"
                                           value="{{ old('city', $customer->city) }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           :required="deliveryMethod === 'delivery'">
                                    @error('city')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="state" class="block text-sm font-medium text-gray-700">County / State</label>
                                    <input type="text" name="state" id="state"
                                           value="{{ old('state', $customer->state) }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code <span class="text-red-500">*</span></label>
                                    <input type="text" name="postal_code" id="postal_code"
                                           value="{{ old('postal_code', $customer->postal_code) }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           :required="deliveryMethod === 'delivery'">
                                    @error('postal_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="country" class="block text-sm font-medium text-gray-700">Country <span class="text-red-500">*</span></label>
                                    <input type="text" name="country" id="country"
                                           value="{{ old('country', $customer->country ?? 'United Kingdom') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           :required="deliveryMethod === 'delivery'">
                                    @error('country')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Collection Info (shown only when collection is selected) --}}
                        <div x-show="deliveryMethod === 'collection'" x-transition class="px-5 py-4 border-t">
                            <div class="flex items-start gap-3 p-3 bg-blue-50 rounded-lg">
                                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <div>
                                    <p class="font-medium text-gray-900">Collection Point</p>
                                    <p class="text-sm text-gray-600 mt-1">North Manchester — we'll email you the full address and collection instructions once your order is confirmed.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Rental Agreements and Signatures --}}
                @if (!empty($rentalAgreements))
                    @foreach ($rentalAgreements as $index => $agreement)
                        <div class="bg-white rounded-lg border overflow-hidden" id="agreement-{{ $agreement['product_id'] }}">
                            <div class="px-5 py-4 border-b bg-amber-50">
                                <h2 class="font-semibold text-gray-900">Rental Agreement: {{ $agreement['product_name'] }}</h2>
                                <p class="text-sm text-gray-500 mt-1">Please read and accept the rental terms below.</p>
                            </div>
                            <div class="px-5 py-4 space-y-4">
                                {{-- Agreement text --}}
                                <div class="max-h-64 overflow-y-auto border rounded-md p-4 bg-gray-50 text-sm text-gray-700 prose prose-sm">
                                    {!! $agreement['agreement_text'] !!}
                                </div>

                                {{-- Acceptance checkbox --}}
                                <div class="flex items-start gap-2">
                                    <input type="checkbox"
                                           name="rental_agreements[{{ $agreement['product_id'] }}]"
                                           id="agreement-accept-{{ $agreement['product_id'] }}"
                                           value="1"
                                           class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           required>
                                    <label for="agreement-accept-{{ $agreement['product_id'] }}" class="text-sm text-gray-700">
                                        I have read and accept the rental agreement for <strong>{{ $agreement['product_name'] }}</strong>.
                                    </label>
                                </div>

                                {{-- Signature pad --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Signature <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mb-2">Please sign below to confirm your agreement.</p>
                                    <div class="border rounded-md bg-white relative">
                                        <canvas id="signature-canvas-{{ $agreement['product_id'] }}"
                                                class="signature-canvas w-full"
                                                width="500"
                                                height="200"
                                                data-product-id="{{ $agreement['product_id'] }}"></canvas>
                                    </div>
                                    <div class="flex items-center justify-between mt-2">
                                        <button type="button"
                                                class="clear-signature-btn text-sm text-red-600 hover:text-red-800 transition"
                                                data-product-id="{{ $agreement['product_id'] }}">
                                            Clear Signature
                                        </button>
                                        <span class="text-xs text-gray-400 signature-status" id="signature-status-{{ $agreement['product_id'] }}">
                                            No signature drawn
                                        </span>
                                    </div>
                                    {{-- Hidden input for base64 signature data --}}
                                    <input type="hidden"
                                           name="signatures[{{ $agreement['product_id'] }}]"
                                           id="signature-data-{{ $agreement['product_id'] }}"
                                           class="signature-data-input"
                                           data-product-id="{{ $agreement['product_id'] }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Right column: Order total and submit --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg border p-6 sticky top-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Order Total</h3>

                    <div class="space-y-2 text-sm border-b pb-4 mb-4">
                        @foreach ($items as $item)
                            <div class="flex justify-between">
                                <span class="text-gray-600 truncate mr-2">{{ $item['name'] }}</span>
                                <span class="text-gray-900 font-medium whitespace-nowrap">&pound;{{ number_format($item['total_price'] ?? $item['price'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($hasPhysicalItems && $deliveryTotal > 0)
                        <div class="flex justify-between text-sm mb-2" id="delivery-charge-row">
                            <span class="text-gray-600">Delivery</span>
                            <span class="text-gray-900 font-medium" id="delivery-charge-amount">&pound;{{ number_format($deliveryTotal, 2) }}</span>
                        </div>
                    @endif

                    {{-- Discount Code Input --}}
                    <div class="border-t pt-4 mb-4">
                        <label for="discount_code" class="block text-sm font-medium text-gray-700 mb-1">Discount Code</label>
                        <div class="flex gap-2">
                            <input type="text" name="discount_code" id="discount_code"
                                   value="{{ old('discount_code') }}"
                                   placeholder="Enter code"
                                   class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Applied at checkout if valid.</p>
                    </div>

                    <div class="flex items-center justify-between mb-6 border-t pt-4">
                        <span class="text-lg font-semibold text-gray-900">Total</span>
                        <span class="text-2xl font-bold text-gray-900">&pound;{{ number_format($total + $deliveryTotal, 2) }}</span>
                    </div>

                    <button type="submit"
                            id="checkout-submit-btn"
                            class="w-full px-5 py-3 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Complete Order
                    </button>

                    <div class="mt-3 text-center">
                        <a href="{{ route('portal.cart.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                            &larr; Back to Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if (!empty($rentalAgreements))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Check if SignaturePad is available (from npm package)
                    if (typeof SignaturePad === 'undefined' && typeof window.SignaturePad === 'undefined') {
                        // Fallback: load from CDN if not bundled
                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js';
                        script.onload = initSignaturePads;
                        document.head.appendChild(script);
                    } else {
                        initSignaturePads();
                    }

                    function initSignaturePads() {
                        const SigPad = window.SignaturePad || SignaturePad;
                        const pads = {};

                        document.querySelectorAll('.signature-canvas').forEach(function(canvas) {
                            const productId = canvas.dataset.productId;

                            // Resize canvas to match display size
                            function resizeCanvas() {
                                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                const rect = canvas.getBoundingClientRect();
                                canvas.width = rect.width * ratio;
                                canvas.height = rect.height * ratio;
                                canvas.getContext('2d').scale(ratio, ratio);
                                canvas.style.width = rect.width + 'px';
                                canvas.style.height = rect.height + 'px';
                            }

                            resizeCanvas();

                            const pad = new SigPad(canvas, {
                                backgroundColor: 'rgb(255, 255, 255)',
                                penColor: 'rgb(0, 0, 0)'
                            });

                            pads[productId] = pad;

                            // Update status on stroke end
                            pad.addEventListener('endStroke', function() {
                                const statusEl = document.getElementById('signature-status-' + productId);
                                const dataInput = document.getElementById('signature-data-' + productId);
                                if (!pad.isEmpty()) {
                                    statusEl.textContent = 'Signature captured';
                                    statusEl.classList.add('text-green-600');
                                    statusEl.classList.remove('text-gray-400');
                                    dataInput.value = pad.toDataURL('image/png');
                                }
                            });
                        });

                        // Clear buttons
                        document.querySelectorAll('.clear-signature-btn').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                const productId = btn.dataset.productId;
                                const pad = pads[productId];
                                if (pad) {
                                    pad.clear();
                                    const statusEl = document.getElementById('signature-status-' + productId);
                                    const dataInput = document.getElementById('signature-data-' + productId);
                                    statusEl.textContent = 'No signature drawn';
                                    statusEl.classList.remove('text-green-600');
                                    statusEl.classList.add('text-gray-400');
                                    dataInput.value = '';
                                }
                            });
                        });

                        // Form submission validation
                        const form = document.getElementById('checkout-form');
                        form.addEventListener('submit', function(e) {
                            let valid = true;

                            // Capture all signature data before submission
                            Object.keys(pads).forEach(function(productId) {
                                const pad = pads[productId];
                                const dataInput = document.getElementById('signature-data-' + productId);
                                const agreementCheckbox = document.getElementById('agreement-accept-' + productId);

                                if (agreementCheckbox && agreementCheckbox.checked) {
                                    if (pad.isEmpty()) {
                                        valid = false;
                                        const statusEl = document.getElementById('signature-status-' + productId);
                                        statusEl.textContent = 'Signature is required';
                                        statusEl.classList.add('text-red-600');
                                        statusEl.classList.remove('text-gray-400', 'text-green-600');
                                    } else {
                                        dataInput.value = pad.toDataURL('image/png');
                                    }
                                }
                            });

                            if (!valid) {
                                e.preventDefault();
                                alert('Please provide a signature for all rental agreements.');
                            }
                        });
                    }
                });
            </script>
        @endpush

        @push('styles')
            <style>
                .signature-canvas {
                    touch-action: none;
                    cursor: crosshair;
                    height: 200px;
                }
            </style>
        @endpush
    @endif
</x-portal-layout>
