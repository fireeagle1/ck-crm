<x-admin-layout>
    <x-slot:title>Order #{{ $order->id }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Order #{{ $order->id }}</h1>
        <a href="{{ route('admin.shop.orders.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
            &larr; Back to Orders
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Order Summary Strip --}}
    <div class="bg-white rounded-lg border p-4 mb-6">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
            <div>
                <span class="text-gray-500">Customer</span>
                <span class="ml-2 font-medium text-gray-900">{{ $order->customer?->company_name ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="text-gray-500">Date</span>
                <span class="ml-2 font-medium text-gray-900">{{ $order->created_at->format('d M Y') }}</span>
            </div>
            <div>
                <span class="text-gray-500">Total</span>
                <span class="ml-2 font-semibold text-gray-900">&pound;{{ number_format($order->total_amount, 2) }}</span>
            </div>
            <div>
                <span class="text-gray-500">Payment</span>
                @php
                    $paymentColors = [
                        'paid' => 'bg-green-100 text-green-700',
                        'paid_offline' => 'bg-green-100 text-green-700',
                        'failed' => 'bg-red-100 text-red-700',
                        'pending' => 'bg-yellow-100 text-yellow-700',
                    ];
                @endphp
                <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ ucwords(str_replace('_', ' ', $order->payment_status)) }}
                </span>
            </div>
            <div>
                <span class="text-gray-500">Fulfilment</span>
                @php
                    $fulfilmentColors = [
                        'completed' => 'bg-green-100 text-green-700',
                        'awaiting_fulfilment' => 'bg-blue-100 text-blue-700',
                        'pending' => 'bg-gray-100 text-gray-700',
                        'cancelled' => 'bg-red-100 text-red-700',
                    ];
                @endphp
                <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $fulfilmentColors[$order->fulfilment_status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ ucwords(str_replace('_', ' ', $order->fulfilment_status)) }}
                </span>
            </div>
            @if ($order->refund_amount > 0)
                <div>
                    <span class="text-gray-500">Refunded</span>
                    <span class="ml-2 text-red-700 font-semibold">&pound;{{ number_format($order->refund_amount, 2) }}</span>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ml-1 {{ $order->refund_status === 'full' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ ucfirst($order->refund_status) }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Delivery Address --}}
            @if ($order->delivery_address_line1)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Delivery Address</h2>
                    </div>
                    <div class="p-4 text-sm text-gray-900">
                        <p>{{ $order->delivery_address_line1 }}</p>
                        @if ($order->delivery_address_line2)<p>{{ $order->delivery_address_line2 }}</p>@endif
                        <p>{{ $order->delivery_city }}@if ($order->delivery_state), {{ $order->delivery_state }}@endif</p>
                        <p>{{ $order->delivery_postal_code }}</p>
                        <p>{{ $order->delivery_country }}</p>
                    </div>
                </div>
            @endif

            {{-- Order Items --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Items</h2>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Product</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Qty</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->product_name }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $typeColors = [
                                            'equipment_rental' => 'bg-purple-100 text-purple-700',
                                            'one_off' => 'bg-amber-100 text-amber-700',
                                            'hosting' => 'bg-blue-100 text-blue-700',
                                        ];
                                        $typeLabels = [
                                            'equipment_rental' => 'Rental',
                                            'one_off' => 'One-Off',
                                            'hosting' => 'Hosting',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typeColors[$item->product_type] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $typeLabels[$item->product_type] ?? ucwords(str_replace('_', ' ', $item->product_type)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->quantity ?? 1 }}</td>
                                <td class="px-4 py-3 text-right font-medium">&pound;{{ number_format($item->price, 2) }}</td>
                            </tr>
                            @if ($item->product && $item->product->delivery_instructions)
                                <tr class="bg-blue-50">
                                    <td colspan="4" class="px-4 py-2 text-xs text-blue-700">
                                        <strong>Delivery/Collection:</strong> {{ $item->product->delivery_instructions }}
                                    </td>
                                </tr>
                            @endif
                            @if ($item->questionAnswers && $item->questionAnswers->isNotEmpty())
                                <tr>
                                    <td colspan="4" class="px-4 py-3">
                                        <div class="mt-1 border-t pt-3">
                                            <h5 class="text-sm font-medium text-gray-600 mb-2">Customer Responses</h5>
                                            <dl class="grid grid-cols-1 gap-2">
                                                @foreach($item->questionAnswers as $answer)
                                                    <div>
                                                        <dt class="text-xs text-gray-500">{{ $answer->question_label }}</dt>
                                                        <dd class="text-sm text-gray-900">{{ $answer->answer_value ?: '—' }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot class="border-t bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right font-semibold text-gray-600">Total</td>
                            <td class="px-4 py-3 text-right font-bold">&pound;{{ number_format($order->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Rental Fulfilment Section (per booking) --}}
            @php
                $rentalItems = $order->items->filter(fn ($item) => $item->booking);
            @endphp
            @foreach ($rentalItems as $item)
                @php
                    $booking = $item->booking;
                    $ctx = $bookingContext[$booking->id] ?? [];
                    $availableAssets = $ctx['availableAssets'] ?? collect();
                    $nextStage = $ctx['nextStage'] ?? null;
                    $preConditions = $ctx['preConditions'] ?? [];
                @endphp
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-purple-50 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-purple-800">Rental: {{ $item->product_name }}</h2>
                        <div class="flex items-center gap-3">
                            <x-qr-code value="BKG-{{ $booking->id }}" :size="48" />
                            @php
                                $bookingStatusColors = [
                                'confirmed' => 'bg-blue-100 text-blue-700',
                                'active' => 'bg-green-100 text-green-700',
                                'returned' => 'bg-gray-100 text-gray-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $bookingStatusColors[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($booking->status) }}
                        </span>
                        </div>
                    </div>

                    <div class="p-4 space-y-5">
                        {{-- Booking details + Fulfilment timeline side-by-side --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Booking Info --}}
                            <div class="text-sm space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Dates</span>
                                    <span class="text-gray-900">{{ $booking->start_date->format('d M Y') }} &mdash; {{ $booking->end_date->format('d M Y') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Duration</span>
                                    <span class="text-gray-900">{{ $booking->start_date->diffInDays($booking->end_date) }} days</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Quantity</span>
                                    <span class="text-gray-900">{{ $booking->quantity }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Total</span>
                                    <span class="text-gray-900 font-semibold">&pound;{{ number_format($booking->total_price, 2) }}</span>
                                </div>
                                @if ($booking->returned_at)
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Returned</span>
                                        <span class="text-gray-900">{{ $booking->returned_at->format('d M Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Fulfilment Timeline --}}
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase mb-2">Fulfilment Progress</p>
                                <x-fulfilment-timeline
                                    :current-stage="$booking->fulfilment_stage"
                                    :labels="\App\View\Components\FulfilmentTimeline::ADMIN_STAGE_LABELS"
                                    layout="horizontal"
                                />
                            </div>
                        </div>

                        {{-- Asset Assignment (ordered/packing stages — override/add more) --}}
                        @if (in_array($booking->fulfilment_stage, ['ordered', 'packing']))
                            @if ($availableAssets->isNotEmpty())
                                <details class="border rounded-lg bg-blue-50" {{ $booking->assignedAssets->isEmpty() ? 'open' : '' }}>
                                    <summary class="px-4 py-2 cursor-pointer text-sm font-semibold text-blue-800 hover:bg-blue-100">
                                        {{ $booking->assignedAssets->isEmpty() ? 'Assign Assets' : 'Assign Additional Assets' }}
                                    </summary>
                                    <div class="p-4 pt-2">
                                        <form method="POST" action="{{ route('admin.shop.orders.assign-assets', [$order, $booking]) }}">
                                            @csrf
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto mb-3">
                                                @foreach ($availableAssets as $asset)
                                                    <label class="flex items-center gap-2 text-sm bg-white p-2 rounded border cursor-pointer hover:border-blue-400">
                                                        <input type="checkbox" name="asset_ids[]" value="{{ $asset->device_id }}" class="rounded border-gray-300 text-blue-600">
                                                        <span class="font-medium">{{ $asset->device_name }}</span>
                                                        @if ($asset->serial_number)
                                                            <span class="text-xs text-gray-500">({{ $asset->serial_number }})</span>
                                                        @endif
                                                    </label>
                                                @endforeach
                                            </div>
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700">
                                                Assign Selected
                                            </button>
                                        </form>
                                    </div>
                                </details>
                            @elseif ($booking->assignedAssets->isEmpty())
                                <div class="border rounded-lg p-3 bg-yellow-50 text-sm text-yellow-700">
                                    No available assets linked to this product. <a href="{{ route('admin.assets.create') }}" class="underline">Create one</a> or link existing assets from the product page.
                                </div>
                            @endif
                        @endif

                        {{-- Packing List --}}
                        @if ($booking->assignedAssets->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-4 py-2 bg-gray-50 border-b">
                                    <h3 class="text-sm font-semibold text-gray-700">Packing List</h3>
                                </div>
                                <table class="w-full text-sm">
                                    <thead class="border-b"><tr>
                                        <th class="px-4 py-2 text-left text-gray-500 font-medium">Device</th>
                                        <th class="px-4 py-2 text-left text-gray-500 font-medium">Serial</th>
                                        <th class="px-4 py-2 text-left text-gray-500 font-medium">Status</th>
                                    </tr></thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($booking->assignedAssets as $ba)
                                            <tr>
                                                <td class="px-4 py-2 text-gray-900">{{ $ba->asset?->device_name ?? 'Unknown' }}</td>
                                                <td class="px-4 py-2 text-gray-600">{{ $ba->asset?->serial_number ?? '—' }}</td>
                                                <td class="px-4 py-2">
                                                    @if ($ba->released_at)
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600">Released</span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Checkout Inspection Form (at ready stage, no inspection yet) --}}
                        @if ($booking->fulfilment_stage === 'ready' && !$booking->checkoutInspection)
                            <div class="border rounded-lg p-4 bg-amber-50">
                                <h3 class="text-sm font-semibold text-amber-800 mb-2">Checkout Inspection</h3>
                                <p class="text-xs text-amber-700 mb-3">Upload photos and notes before handing equipment to the customer.</p>
                                <form method="POST" action="{{ route('admin.shop.orders.inspect', [$order, $booking]) }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="space-y-3">
                                        <div>
                                            <input type="file" name="photos[]" multiple accept="image/jpeg,image/png" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-amber-100 file:text-amber-700 hover:file:bg-amber-200">
                                            <p class="text-xs text-gray-400 mt-1">JPEG/PNG, max 10MB each, up to 10 photos</p>
                                        </div>
                                        <textarea name="condition_notes" rows="2" placeholder="Condition notes..." class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-amber-600 text-white rounded text-sm font-medium hover:bg-amber-700">
                                            Submit &amp; Check Out
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        {{-- Return Inspection Form (at checked_out/returned stage, no return inspection yet) --}}
                        @if (in_array($booking->fulfilment_stage, ['checked_out', 'returned']) && !$booking->returnInspection)
                            <div class="border rounded-lg p-4 bg-purple-50">
                                <h3 class="text-sm font-semibold text-purple-800 mb-2">Return Inspection</h3>
                                <p class="text-xs text-purple-700 mb-3">Record the condition of returned equipment with photos.</p>
                                <form method="POST" action="{{ route('admin.shop.orders.inspect', [$order, $booking]) }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="space-y-3">
                                        <div>
                                            <input type="file" name="photos[]" multiple accept="image/jpeg,image/png" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-purple-100 file:text-purple-700 hover:file:bg-purple-200">
                                            <p class="text-xs text-gray-400 mt-1">JPEG/PNG, max 10MB each, up to 10 photos</p>
                                        </div>
                                        <textarea name="condition_notes" rows="2" placeholder="Condition notes..." class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                                            <input type="checkbox" name="damage_flagged" value="1" class="rounded border-gray-300 text-red-600">
                                            <span class="text-gray-700">Flag damage detected</span>
                                        </label>
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-purple-600 text-white rounded text-sm font-medium hover:bg-purple-700">
                                            Submit &amp; Complete
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        {{-- Inspection Gallery --}}
                        @if ($booking->checkoutInspection || $booking->returnInspection)
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-4 py-2 bg-gray-50 border-b">
                                    <h3 class="text-sm font-semibold text-gray-700">Inspections</h3>
                                </div>
                                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {{-- Checkout --}}
                                    <div>
                                        <p class="text-xs font-semibold text-gray-600 mb-1">Checkout</p>
                                        @if ($booking->checkoutInspection)
                                            <p class="text-xs text-gray-500 mb-2">{{ $booking->checkoutInspection->inspector?->name }} &middot; {{ $booking->checkoutInspection->inspected_at->format('d M Y H:i') }}</p>
                                            @if ($booking->checkoutInspection->condition_notes)
                                                <p class="text-xs text-gray-600 italic mb-2">"{{ $booking->checkoutInspection->condition_notes }}"</p>
                                            @endif
                                            @if (!empty($booking->checkoutInspection->photos))
                                                <div class="grid grid-cols-3 gap-1">
                                                    @foreach ($booking->checkoutInspection->photos as $photo)
                                                        <img src="{{ route('admin.shop.bookings.inspectionPhoto', $photo) }}" alt="Checkout" class="rounded border object-cover h-20 w-full">
                                                    @endforeach
                                                </div>
                                            @endif
                                        @else
                                            <p class="text-xs text-gray-400">Pending</p>
                                        @endif
                                    </div>
                                    {{-- Return --}}
                                    <div>
                                        <p class="text-xs font-semibold text-gray-600 mb-1">Return</p>
                                        @if ($booking->returnInspection)
                                            <p class="text-xs text-gray-500 mb-2">{{ $booking->returnInspection->inspector?->name }} &middot; {{ $booking->returnInspection->inspected_at->format('d M Y H:i') }}</p>
                                            @if ($booking->returnInspection->damage_flagged)
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700 mb-2">Damage Flagged</span>
                                            @endif
                                            @if ($booking->returnInspection->condition_notes)
                                                <p class="text-xs text-gray-600 italic mb-2">"{{ $booking->returnInspection->condition_notes }}"</p>
                                            @endif
                                            @if (!empty($booking->returnInspection->photos))
                                                <div class="grid grid-cols-3 gap-1">
                                                    @foreach ($booking->returnInspection->photos as $photo)
                                                        <img src="{{ route('admin.shop.bookings.inspectionPhoto', $photo) }}" alt="Return" class="rounded border object-cover h-20 w-full">
                                                    @endforeach
                                                </div>
                                            @endif
                                        @else
                                            <p class="text-xs text-gray-400">Pending</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Advance Stage Button --}}
                        @if ($nextStage && $booking->fulfilment_stage !== 'ready' && !in_array($booking->fulfilment_stage, ['checked_out', 'returned']))
                            <div class="flex items-center gap-3">
                                @if (!empty($preConditions))
                                    <div class="text-xs text-yellow-700">
                                        @foreach ($preConditions as $c)
                                            <span class="block">{{ $c }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('admin.shop.orders.advance-stage', [$order, $booking]) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white rounded text-sm font-medium hover:bg-indigo-700">
                                            Advance to {{ ucwords(str_replace('_', ' ', $nextStage)) }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif

                        {{-- Booking Confirmation PDF Download --}}
                        <a href="{{ route('admin.shop.bookings.downloadConfirmation', $booking) }}"
                           class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-600 text-white rounded text-sm font-medium hover:bg-purple-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Download Booking Confirmation
                        </a>

                        {{-- Signature & Agreement (collapsible) --}}
                        @if ($booking->signature_data || $booking->agreement_text_snapshot)
                            <details class="border rounded-lg">
                                <summary class="px-4 py-2 bg-gray-50 cursor-pointer text-sm font-medium text-gray-700 hover:bg-gray-100">
                                    Agreement &amp; Signature
                                </summary>
                                <div class="p-4 space-y-3">
                                    @if ($booking->signature_data)
                                        <div>
                                            <p class="text-xs font-medium text-gray-500 mb-1">Signature</p>
                                            <div class="bg-gray-50 border rounded p-2 inline-block">
                                                <img src="{{ str_starts_with($booking->signature_data, 'data:') ? $booking->signature_data : 'data:image/png;base64,' . $booking->signature_data }}" alt="Signature" class="max-h-20">
                                            </div>
                                            @if ($booking->agreement_accepted_at)
                                                <p class="text-xs text-gray-400 mt-1">Signed {{ $booking->agreement_accepted_at->format('d M Y H:i') }}</p>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($booking->agreement_text_snapshot)
                                        <div>
                                            <p class="text-xs font-medium text-gray-500 mb-1">Rental Agreement</p>
                                            <div class="prose prose-sm max-w-none text-gray-600 text-xs max-h-48 overflow-y-auto border rounded p-3 bg-gray-50">
                                                {!! $booking->agreement_text_snapshot !!}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Stripe References (collapsible) --}}
            @if ($order->stripe_checkout_session_id || $order->stripe_payment_intent_id || $order->discount_code)
                <details class="bg-white rounded-lg shadow-sm border">
                    <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Payment &amp; Reference Details
                    </summary>
                    <div class="px-4 pb-4 text-sm space-y-2">
                        @if ($order->stripe_checkout_session_id)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Checkout Session</span>
                                <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $order->stripe_checkout_session_id }}</code>
                            </div>
                        @endif
                        @if ($order->stripe_payment_intent_id)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Payment Intent</span>
                                <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $order->stripe_payment_intent_id }}</code>
                            </div>
                        @endif
                        @if ($order->delivery_method)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Delivery Method</span>
                                <span class="capitalize">{{ $order->delivery_method }}</span>
                            </div>
                        @endif
                        @if ($order->delivery_charge > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Delivery Charge</span>
                                <span>&pound;{{ number_format($order->delivery_charge, 2) }}</span>
                            </div>
                        @endif
                        @if ($order->discount_code)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Discount</span>
                                <span><code class="text-xs bg-purple-100 px-1.5 py-0.5 rounded text-purple-700">{{ $order->discount_code }}</code> -&pound;{{ number_format($order->discount_amount, 2) }}</span>
                            </div>
                        @endif
                    </div>
                </details>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            {{-- QR Code --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="p-4 flex justify-center">
                    <x-qr-code value="ORD-{{ $order->id }}" :size="120" label="ORD-{{ $order->id }}" />
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Actions</h2>
                </div>
                <div class="p-4 space-y-3">
                    {{-- Download PDF --}}
                    @if ($order->invoice_pdf_path)
                        <a href="{{ route('admin.shop.orders.download-pdf', $order) }}" class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Download Invoice
                        </a>
                    @endif

                    {{-- Mark Paid Offline --}}
                    @if (!in_array($order->payment_status, ['paid', 'paid_offline']))
                        <form method="POST" action="{{ route('admin.shop.orders.mark-paid-offline', $order) }}">
                            @csrf
                            <button type="submit" onclick="return confirm('Mark as paid offline?')" class="w-full inline-flex items-center justify-center px-3 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700">
                                Mark Paid Offline
                            </button>
                        </form>
                    @endif

                    {{-- Mark Fulfilled --}}
                    @if ($order->fulfilment_status !== 'completed' && $order->fulfilment_status !== 'cancelled')
                        <form method="POST" action="{{ route('admin.shop.orders.fulfil', $order) }}">
                            @csrf
                            <button type="submit" onclick="return confirm('Mark this order as fulfilled?')" class="w-full inline-flex items-center justify-center px-3 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
                                Mark Fulfilled
                            </button>
                        </form>
                    @endif

                    {{-- Cancel --}}
                    @if ($order->fulfilment_status !== 'cancelled')
                        <form method="POST" action="{{ route('admin.shop.orders.cancel', $order) }}">
                            @csrf
                            <button type="submit" onclick="return confirm('Cancel this order and all associated bookings?')" class="w-full inline-flex items-center justify-center px-3 py-2 border border-red-300 text-red-700 rounded-md text-sm font-medium hover:bg-red-50">
                                Cancel Order
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Refund --}}
            @if ($order->stripe_payment_intent_id && $order->refund_status !== 'full')
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Refund</h2>
                    </div>
                    <div class="p-4">
                        @php $maxRefundable = (float) $order->total_amount - (float) $order->refund_amount; @endphp
                        <p class="text-xs text-gray-500 mb-3">Max: &pound;{{ number_format($maxRefundable, 2) }}</p>
                        <form method="POST" action="{{ route('admin.shop.orders.refund', $order) }}">
                            @csrf
                            <div class="space-y-2">
                                <input type="number" name="refund_amount" step="0.01" min="0.01" max="{{ $maxRefundable }}" value="{{ $maxRefundable }}" class="w-full rounded-md border-gray-300 text-sm">
                                <select name="refund_reason" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="requested_by_customer">Customer Request</option>
                                    <option value="duplicate">Duplicate</option>
                                    <option value="fraudulent">Fraudulent</option>
                                </select>
                                <button type="submit" onclick="return confirm('Process this refund?')" class="w-full inline-flex items-center justify-center px-3 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">
                                    Process Refund
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Admin Notes --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Notes</h2>
                </div>
                <div class="p-4">
                    @if ($order->admin_notes)
                        <div class="text-xs text-gray-600 whitespace-pre-line mb-3 max-h-32 overflow-y-auto bg-gray-50 rounded p-2 border">{{ $order->admin_notes }}</div>
                    @endif
                    <form method="POST" action="{{ route('admin.shop.orders.note', $order) }}">
                        @csrf
                        <textarea name="note" rows="2" placeholder="Add a note..." class="w-full rounded-md border-gray-300 text-sm mb-2"></textarea>
                        <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-1.5 bg-gray-700 text-white rounded-md text-sm font-medium hover:bg-gray-800">
                            Add Note
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
