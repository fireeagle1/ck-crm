<x-portal-layout>
    <x-slot:title>{{ $product->name }}</x-slot:title>

    {{-- Back to shop link --}}
    <a href="{{ route('portal.shop.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6 transition">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Shop
    </a>

    <div class="bg-white rounded-lg border overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
            {{-- Product image --}}
            <div class="h-64 md:h-full bg-gray-100 min-h-[300px]">
                @if ($product->image_path)
                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-24 h-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                @endif
            </div>

            {{-- Product details --}}
            <div class="p-6 md:p-8 flex flex-col">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
                    <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                        @switch($product->product_type)
                            @case('hosting') bg-blue-100 text-blue-700 @break
                            @case('equipment_rental') bg-amber-100 text-amber-700 @break
                            @case('one_off') bg-green-100 text-green-700 @break
                        @endswitch
                    ">
                        {{ str_replace('_', ' ', ucfirst($product->product_type)) }}
                    </span>
                </div>

                {{-- Price --}}
                <div class="mb-4">
                    <p class="text-3xl font-bold text-gray-900">
                        &pound;{{ number_format($product->price, 2) }}
                    </p>
                    @if ($product->billing_frequency)
                        <p class="text-sm text-gray-500 mt-1">
                            Billed {{ $product->billing_frequency }}
                        </p>
                    @elseif ($product->isEquipmentRental())
                        <p class="text-sm text-gray-500 mt-1">per day</p>
                    @endif
                </div>

                {{-- Description --}}
                <div class="text-sm text-gray-600 leading-relaxed mb-6 flex-1 prose prose-sm max-w-none">
                    {!! $product->description !!}
                </div>

                {{-- Delivery instructions --}}
                @if (!empty($deliveryInstructions ?? null))
                    <div class="mb-4 rounded-md bg-gray-50 border border-gray-200 p-3">
                        <p class="text-xs font-semibold text-gray-700 mb-1">Delivery / Collection</p>
                        <p class="text-sm text-gray-600">{{ $deliveryInstructions }}</p>
                    </div>
                @endif

                {{-- Error message --}}
                @if (session('error'))
                    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
                        <p class="text-sm text-red-700">{{ session('error') }}</p>
                    </div>
                @endif

                {{-- Availability and Add to Cart --}}
                @if ($product->isAvailable())
                    <form method="POST" action="{{ route('portal.cart.add', $product) }}" id="add-to-cart-form">
                        @csrf

                        {{-- Equipment Rental: Visual Availability Calendar --}}
                        @if ($product->isEquipmentRental())
                            <div class="space-y-4 mb-4">
                                {{-- Inline availability calendar --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Rental Dates</label>
                                    <div id="availability-calendar" class="border border-gray-200 rounded-lg overflow-hidden"></div>

                                    {{-- Legend --}}
                                    <div class="flex flex-wrap gap-3 mt-2.5 px-1">
                                        <span class="inline-flex items-center text-xs text-gray-600">
                                            <span class="w-3 h-3 rounded-sm bg-green-100 border border-green-300 mr-1.5"></span>
                                            Available
                                        </span>
                                        <span class="inline-flex items-center text-xs text-gray-600">
                                            <span class="w-3 h-3 rounded-sm bg-amber-100 border border-amber-300 mr-1.5"></span>
                                            Limited
                                        </span>
                                        <span class="inline-flex items-center text-xs text-gray-600">
                                            <span class="w-3 h-3 rounded-sm bg-red-100 border border-red-300 mr-1.5"></span>
                                            Fully Booked
                                        </span>
                                        <span class="inline-flex items-center text-xs text-gray-600">
                                            <span class="w-3 h-3 rounded-sm bg-blue-200 border border-blue-400 mr-1.5"></span>
                                            Selected
                                        </span>
                                    </div>

                                    @if (!empty($minRentalDays))
                                        <p class="text-xs text-gray-500 mt-1.5">Minimum rental period: {{ $minRentalDays }} {{ Str::plural('day', $minRentalDays) }}</p>
                                    @endif
                                    
                                </div>

                                {{-- Hidden date inputs for form submission --}}
                                <input type="hidden" name="rental_start_date" id="rental_start_date" value="{{ old('rental_start_date') }}" required>
                                <input type="hidden" name="rental_end_date" id="rental_end_date" value="{{ old('rental_end_date') }}" required>

                                {{-- Selected dates display --}}
                                <div id="selected-dates-display" class="hidden rounded-md bg-gray-50 border border-gray-200 p-3">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-gray-700">
                                            <span class="font-medium" id="selected-start-label">—</span>
                                            <svg class="inline w-4 h-4 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                            <span class="font-medium" id="selected-end-label">—</span>
                                        </div>
                                        <button type="button" id="clear-dates-btn" class="text-xs text-red-600 hover:text-red-800 font-medium">Clear</button>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1" id="selected-days-count"></p>
                                </div>

                                {{-- Quantity --}}
                                <div>
                                    <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                    <input type="number" name="quantity" id="quantity" min="1" max="{{ $maxQuantity ?? 99 }}" value="{{ old('quantity', 1) }}"
                                           class="w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <p class="text-xs text-gray-500 mt-1"><span id="available-units-label">{{ $maxQuantity ?? '?' }}</span> available</p>
                                </div>

                                {{-- Calculated total display --}}
                                <div id="rental-total-display" class="hidden rounded-md bg-blue-50 border border-blue-200 p-3">
                                    <p class="text-sm text-blue-700">
                                        Estimated total: <span id="rental-total-amount" class="font-semibold">&pound;0.00</span>
                                        <span id="rental-total-breakdown" class="text-xs text-blue-600 block mt-0.5"></span>
                                    </p>
                                </div>
                            </div>

                            {{-- Rental agreement notice --}}
                            @if (!empty($rentalAgreementText))
                                <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 p-3">
                                    <p class="text-xs font-semibold text-amber-700 mb-1">Rental Agreement</p>
                                    <p class="text-xs text-amber-600">A rental agreement must be accepted at checkout before completing this booking.</p>
                                </div>
                            @endif
                        @endif

                        {{-- One-Off: Quantity input --}}
                        @if ($product->isOneOff())
                            <div class="mb-4">
                                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                <input type="number" name="quantity" id="quantity" min="1" max="{{ $maxQuantity }}" value="{{ old('quantity', 1) }}"
                                       class="w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">{{ $maxQuantity }} in stock</p>
                            </div>
                        @endif

                        {{-- Hosting: Domain name input --}}
                        @if ($product->isHosting())
                            <div class="mb-4">
                                <label for="domain_name" class="block text-sm font-medium text-gray-700 mb-1">Domain Name</label>
                                <input type="text" name="domain_name" id="domain_name"
                                       value="{{ old('domain_name') }}"
                                       placeholder="e.g. example.com"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                       required>
                                <p class="text-xs text-gray-500 mt-1">Enter the domain name for your hosting account.</p>
                            </div>
                        @endif

                        <button type="submit" class="w-full px-5 py-3 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                            Add to Cart
                        </button>
                    </form>
                @else
                    <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-center">
                        <p class="text-sm font-semibold text-red-700">Out of Stock</p>
                        <p class="text-xs text-red-600 mt-1">This product is currently unavailable for purchase.</p>
                    </div>
                    <button disabled class="w-full mt-3 px-5 py-3 bg-gray-300 text-gray-500 rounded-md text-sm font-semibold cursor-not-allowed">
                        Add to Cart
                    </button>
                @endif

                {{-- Added to cart prompt --}}
                @if (session('added_to_cart'))
                    <div class="mt-4 rounded-lg bg-green-50 border border-green-200 p-5 text-center" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })">
                        <svg class="mx-auto w-8 h-8 text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-green-800 mb-1">'{{ session('added_to_cart') }}' added to your cart</p>
                        <p class="text-xs text-green-700 mb-4">What would you like to do next?</p>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <a href="{{ route('portal.cart.index') }}" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition text-center">
                                Checkout Now
                            </a>
                            <a href="{{ route('portal.shop.index') }}" class="flex-1 px-4 py-2.5 bg-white text-gray-700 border border-gray-300 rounded-md text-sm font-semibold hover:bg-gray-50 transition text-center">
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Success message --}}
                @if (session('success'))
                    <div class="mt-4 rounded-lg bg-green-50 border border-green-200 p-3 text-center">
                        <p class="text-sm text-green-700">{{ session('success') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Availability Calendar for rental products --}}
    @if ($product->isEquipmentRental())
        @push('styles')
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
            <style>
                /* Calendar container styling */
                #availability-calendar .flatpickr-calendar {
                    box-shadow: none !important;
                    border: none !important;
                    width: 100% !important;
                    max-width: 100% !important;
                }
                #availability-calendar .flatpickr-months {
                    background: #f8fafc;
                    border-bottom: 1px solid #e2e8f0;
                    padding: 4px 0;
                }
                #availability-calendar .flatpickr-month {
                    height: 36px;
                }
                #availability-calendar .flatpickr-current-month {
                    font-size: 0.9rem;
                    font-weight: 600;
                }
                #availability-calendar .flatpickr-weekdays {
                    background: #f8fafc;
                    border-bottom: 1px solid #f1f5f9;
                }
                #availability-calendar .flatpickr-weekday {
                    color: #64748b;
                    font-size: 0.7rem;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                #availability-calendar .dayContainer {
                    width: 100% !important;
                    max-width: 100% !important;
                    min-width: 100% !important;
                }
                #availability-calendar .flatpickr-days {
                    width: 100% !important;
                }
                #availability-calendar .flatpickr-day {
                    max-width: none;
                    height: 38px;
                    line-height: 38px;
                    border-radius: 6px;
                    font-size: 0.8rem;
                    font-weight: 500;
                    margin: 1px;
                    transition: all 0.15s ease;
                }

                /* Available dates — subtle green tint */
                .flatpickr-day.day-available:not(.selected):not(.startRange):not(.endRange):not(.inRange) {
                    background: #ecfdf5 !important;
                    border-color: #a7f3d0 !important;
                    color: #065f46 !important;
                }
                .flatpickr-day.day-available:not(.selected):not(.startRange):not(.endRange):not(.inRange):hover {
                    background: #d1fae5 !important;
                    border-color: #6ee7b7 !important;
                }

                /* Limited availability — amber tint */
                .flatpickr-day.day-limited:not(.selected):not(.startRange):not(.endRange):not(.inRange) {
                    background: #fffbeb !important;
                    border-color: #fcd34d !important;
                    color: #92400e !important;
                }
                .flatpickr-day.day-limited:not(.selected):not(.startRange):not(.endRange):not(.inRange):hover {
                    background: #fef3c7 !important;
                    border-color: #f59e0b !important;
                }

                /* Fully booked / unavailable */
                .flatpickr-day.day-unavailable,
                .flatpickr-day.day-unavailable:hover {
                    background: #fef2f2 !important;
                    color: #991b1b !important;
                    border-color: #fecaca !important;
                    text-decoration: line-through;
                    cursor: not-allowed !important;
                    opacity: 0.8 !important;
                }

                /* Selected range styling */
                .flatpickr-day.selected,
                .flatpickr-day.startRange,
                .flatpickr-day.endRange {
                    background: #2563eb !important;
                    border-color: #2563eb !important;
                    color: #fff !important;
                }
                .flatpickr-day.inRange {
                    background: #dbeafe !important;
                    border-color: #93c5fd !important;
                    color: #1e40af !important;
                    box-shadow: none !important;
                }
                .flatpickr-day.inRange:hover {
                    background: #bfdbfe !important;
                    border-color: #60a5fa !important;
                }

                /* Today marker */
                .flatpickr-day.today:not(.selected):not(.startRange):not(.endRange) {
                    border-color: #3b82f6 !important;
                    border-width: 2px;
                }

                /* Past dates */
                .flatpickr-day.flatpickr-disabled {
                    opacity: 0.35 !important;
                    text-decoration: none !important;
                    background: transparent !important;
                }
            </style>
        @endpush

        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const unavailableDates = @json(json_decode($unavailableDates ?? '[]'));
                    const bookedUnitsPerDay = @json(json_decode($bookedUnitsPerDay ?? '{}'));
                    const totalStock = {{ $totalStock ?? 1 }};
                    const minRentalDays = {{ $minRentalDays ?? 1 }};
                    const pricePerDay = {{ $product->price }};
                    const productId = {{ $product->id }};
                    const csrfToken = '{{ csrf_token() }}';

                    const startInput = document.getElementById('rental_start_date');
                    const endInput = document.getElementById('rental_end_date');
                    const quantityInput = document.getElementById('quantity');
                    const totalDisplay = document.getElementById('rental-total-display');
                    const totalAmount = document.getElementById('rental-total-amount');
                    const totalBreakdown = document.getElementById('rental-total-breakdown');
                    const selectedDatesDisplay = document.getElementById('selected-dates-display');
                    const selectedStartLabel = document.getElementById('selected-start-label');
                    const selectedEndLabel = document.getElementById('selected-end-label');
                    const selectedDaysCount = document.getElementById('selected-days-count');
                    const clearDatesBtn = document.getElementById('clear-dates-btn');
                    const availableUnitsLabel = document.getElementById('available-units-label');

                    /**
                     * Determine the status of a date:
                     * - 'unavailable': fully booked (booked >= stock) or in unavailableDates list
                     * - 'limited': some units booked but not all (> 50% used)
                     * - 'available': plenty of availability
                     */
                    function getDateStatus(dateStr) {
                        if (unavailableDates.includes(dateStr)) {
                            return 'unavailable';
                        }
                        const booked = bookedUnitsPerDay[dateStr] || 0;
                        if (booked >= totalStock) {
                            return 'unavailable';
                        }
                        if (booked > 0 && booked >= totalStock * 0.5) {
                            return 'limited';
                        }
                        if (booked > 0) {
                            return 'limited';
                        }
                        return 'available';
                    }

                    function formatDateLabel(dateStr) {
                        const d = new Date(dateStr + 'T12:00:00');
                        return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
                    }

                    function calculateTotal() {
                        const start = startInput.value;
                        const end = endInput.value;
                        const qty = parseInt(quantityInput.value) || 1;

                        if (start && end) {
                            const startDate = new Date(start + 'T12:00:00');
                            const endDate = new Date(end + 'T12:00:00');
                            const days = Math.round((endDate - startDate) / (1000 * 60 * 60 * 24));

                            if (days > 0) {
                                const total = (pricePerDay * days * qty).toFixed(2);
                                totalAmount.innerHTML = '&pound;' + total;
                                totalBreakdown.textContent = `£${pricePerDay.toFixed(2)} × ${days} day${days > 1 ? 's' : ''} × ${qty} unit${qty > 1 ? 's' : ''}`;
                                totalDisplay.classList.remove('hidden');
                                return;
                            }
                        }
                        totalDisplay.classList.add('hidden');
                    }

                    function updateSelectedDisplay() {
                        const start = startInput.value;
                        const end = endInput.value;

                        if (start && end) {
                            selectedStartLabel.textContent = formatDateLabel(start);
                            selectedEndLabel.textContent = formatDateLabel(end);
                            const days = Math.round((new Date(end + 'T12:00:00') - new Date(start + 'T12:00:00')) / (1000 * 60 * 60 * 24));
                            selectedDaysCount.textContent = `${days} day${days !== 1 ? 's' : ''} rental`;
                            selectedDatesDisplay.classList.remove('hidden');
                        } else if (start) {
                            selectedStartLabel.textContent = formatDateLabel(start);
                            selectedEndLabel.textContent = '—';
                            selectedDaysCount.textContent = 'Select an end date';
                            selectedDatesDisplay.classList.remove('hidden');
                        } else {
                            selectedDatesDisplay.classList.add('hidden');
                        }
                    }

                    /**
                     * Check if any date in the selected range crosses an unavailable date.
                     * If so, limit the selection.
                     */
                    function hasUnavailableInRange(start, end) {
                        const startDate = new Date(start + 'T12:00:00');
                        const endDate = new Date(end + 'T12:00:00');
                        const current = new Date(startDate);
                        while (current <= endDate) {
                            const key = current.toISOString().split('T')[0];
                            if (unavailableDates.includes(key)) {
                                return true;
                            }
                            current.setDate(current.getDate() + 1);
                        }
                        return false;
                    }

                    // Initialise Flatpickr as an inline range picker
                    const calendar = flatpickr('#availability-calendar', {
                        inline: true,
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        minDate: 'today',
                        showMonths: 1,
                        disable: [
                            function(date) {
                                const dateStr = date.toISOString().split('T')[0];
                                return unavailableDates.includes(dateStr);
                            }
                        ],
                        onChange: function(selectedDates, dateStr) {
                            if (selectedDates.length === 2) {
                                const start = selectedDates[0].toISOString().split('T')[0];
                                const end = selectedDates[1].toISOString().split('T')[0];
                                const days = Math.round((selectedDates[1] - selectedDates[0]) / (1000 * 60 * 60 * 24));

                                // Enforce minimum rental days
                                if (days < minRentalDays) {
                                    const minEnd = new Date(selectedDates[0]);
                                    minEnd.setDate(minEnd.getDate() + minRentalDays);
                                    calendar.setDate([selectedDates[0], minEnd], true);
                                    return;
                                }

                                // Check for unavailable dates in range
                                if (hasUnavailableInRange(start, end)) {
                                    // Find the first unavailable date and limit to the day before
                                    const current = new Date(selectedDates[0]);
                                    let lastAvailable = new Date(selectedDates[0]);
                                    while (current <= selectedDates[1]) {
                                        const key = current.toISOString().split('T')[0];
                                        if (unavailableDates.includes(key)) break;
                                        lastAvailable = new Date(current);
                                        current.setDate(current.getDate() + 1);
                                    }
                                    if (lastAvailable > selectedDates[0]) {
                                        calendar.setDate([selectedDates[0], lastAvailable], true);
                                    } else {
                                        calendar.clear();
                                        startInput.value = '';
                                        endInput.value = '';
                                    }
                                    return;
                                }

                                startInput.value = start;
                                endInput.value = end;
                            } else if (selectedDates.length === 1) {
                                startInput.value = selectedDates[0].toISOString().split('T')[0];
                                endInput.value = '';
                            } else {
                                startInput.value = '';
                                endInput.value = '';
                            }
                            updateSelectedDisplay();
                            calculateTotal();
                        },
                        onDayCreate: function(dObj, dStr, fp, dayElem) {
                            const date = dayElem.dateObj;
                            if (!date) return;

                            const dateStr = date.toISOString().split('T')[0];
                            const today = new Date();
                            today.setHours(0,0,0,0);

                            // Only style future/today dates
                            if (date >= today) {
                                const status = getDateStatus(dateStr);
                                if (status === 'unavailable') {
                                    dayElem.classList.add('day-unavailable');
                                } else if (status === 'limited') {
                                    dayElem.classList.add('day-limited');
                                    const booked = bookedUnitsPerDay[dateStr] || 0;
                                    const remaining = totalStock - booked;
                                    dayElem.title = `${remaining} of ${totalStock} unit${totalStock > 1 ? 's' : ''} available`;
                                } else {
                                    dayElem.classList.add('day-available');
                                    dayElem.title = `${totalStock} unit${totalStock > 1 ? 's' : ''} available`;
                                }
                            }
                        }
                    });

                    // Clear dates button
                    clearDatesBtn.addEventListener('click', function() {
                        calendar.clear();
                        startInput.value = '';
                        endInput.value = '';
                        updateSelectedDisplay();
                        calculateTotal();
                    });

                    // When quantity changes, dynamically re-check availability via AJAX
                    let debounceTimer;
                    quantityInput.addEventListener('input', function() {
                        calculateTotal();
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(refreshAvailability, 400);
                    });

                    function refreshAvailability() {
                        const qty = parseInt(quantityInput.value) || 1;
                        if (qty < 1) return;

                        const rangeStart = new Date().toISOString().split('T')[0];
                        const rangeEndDate = new Date();
                        rangeEndDate.setDate(rangeEndDate.getDate() + 90);
                        const rangeEnd = rangeEndDate.toISOString().split('T')[0];

                        fetch(`{{ route('portal.bookings.unavailableDates') }}?product_id=${productId}&range_start=${rangeStart}&range_end=${rangeEnd}&quantity=${qty}`)
                            .then(res => res.json())
                            .then(function(newUnavailable) {
                                // Update the unavailable dates array
                                unavailableDates.length = 0;
                                newUnavailable.forEach(d => unavailableDates.push(d));

                                // Update available units label
                                const remaining = Math.max(0, totalStock - qty + 1);
                                availableUnitsLabel.textContent = totalStock;

                                // Redraw the calendar
                                calendar.redraw();

                                // If current selection now overlaps unavailable dates, clear it
                                if (startInput.value && endInput.value) {
                                    if (hasUnavailableInRange(startInput.value, endInput.value)) {
                                        calendar.clear();
                                        startInput.value = '';
                                        endInput.value = '';
                                        updateSelectedDisplay();
                                        calculateTotal();
                                    }
                                }
                            })
                            .catch(function() {
                                // Silently fail — the static data remains usable
                            });
                    }

                    // Restore old values if validation failed
                    @if(old('rental_start_date') && old('rental_end_date'))
                        const oldStart = '{{ old('rental_start_date') }}';
                        const oldEnd = '{{ old('rental_end_date') }}';
                        calendar.setDate([oldStart, oldEnd], true);
                        startInput.value = oldStart;
                        endInput.value = oldEnd;
                        updateSelectedDisplay();
                        calculateTotal();
                    @endif
                });
            </script>
        @endpush
    @endif
</x-portal-layout>
