<x-admin-layout>
    <x-slot:title>Fulfilment — Booking #{{ $booking->id }}</x-slot:title>

    @php
        $stages = \App\Services\FulfilmentStageService::STAGES;
        $currentStageIndex = array_search($booking->fulfilment_stage, $stages);
    @endphp

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Fulfilment — Booking #{{ $booking->id }}</h1>
        <a href="{{ route('admin.fulfilment.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
            &larr; Back to Queue
        </a>
    </div>

    {{-- Flash Messages --}}
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Header: Booking Details --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Booking Details</h2>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-y-3 gap-x-4 text-sm">
                        <div>
                            <span class="text-gray-500 font-medium block">Product</span>
                            <span class="text-gray-900 font-semibold">{{ $booking->product?->name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 font-medium block">Customer</span>
                            <span class="text-gray-900">{{ $booking->customer?->company_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 font-medium block">Total Price</span>
                            <span class="text-gray-900 font-semibold">&pound;{{ number_format($booking->total_price, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 font-medium block">Start Date</span>
                            <span class="text-gray-900">{{ $booking->start_date->format('d M Y') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 font-medium block">End Date</span>
                            <span class="text-gray-900">{{ $booking->end_date->format('d M Y') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 font-medium block">Quantity</span>
                            <span class="text-gray-900">{{ $booking->quantity }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Asset Assignment Panel (shown during ordered/packing stage) --}}
            @if (in_array($booking->fulfilment_stage, ['ordered', 'packing']) && $availableAssets->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-700">Assign Assets</h2>
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-500 mb-3">Select assets to assign to this booking. Only available assets linked to the product are shown.</p>
                        <form method="POST" action="{{ route('admin.fulfilment.assignAssets', $booking) }}">
                            @csrf
                            <div class="space-y-2 max-h-60 overflow-y-auto border rounded-md p-3 bg-gray-50">
                                @foreach ($availableAssets as $asset)
                                    <label class="flex items-center space-x-3 cursor-pointer hover:bg-white p-2 rounded">
                                        <input type="checkbox" name="asset_ids[]" value="{{ $asset->device_id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-900 font-medium">{{ $asset->device_name }}</span>
                                        @if ($asset->serial_number)
                                            <span class="text-xs text-gray-500">({{ $asset->serial_number }})</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            @error('asset_ids')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="mt-3 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                                Assign Selected Assets
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Packing List Panel (shown when assets are assigned) --}}
            @if ($booking->assignedAssets->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Packing List</h2>
                    </div>
                    <div class="p-4">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="pb-2 font-medium">Device Name</th>
                                    <th class="pb-2 font-medium">Serial Number</th>
                                    <th class="pb-2 font-medium">Assigned Date</th>
                                    <th class="pb-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($booking->assignedAssets as $bookingAsset)
                                    <tr>
                                        <td class="py-2 text-gray-900">{{ $bookingAsset->asset?->device_name ?? 'Unknown' }}</td>
                                        <td class="py-2 text-gray-600">{{ $bookingAsset->asset?->serial_number ?? '—' }}</td>
                                        <td class="py-2 text-gray-600">{{ $bookingAsset->assigned_at->format('d M Y H:i') }}</td>
                                        <td class="py-2">
                                            @if ($bookingAsset->released_at)
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">Released</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Checkout Inspection Panel (shown when advancing to checked_out, i.e. at ready stage) --}}
            @if ($booking->fulfilment_stage === 'ready' && !$booking->checkoutInspection)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-amber-50">
                        <h2 class="text-sm font-semibold text-amber-700">Checkout Inspection</h2>
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-500 mb-3">Upload photos and record the condition of items before handing them to the customer.</p>
                        <form method="POST" action="{{ route('admin.fulfilment.inspect', $booking) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Photos (required, max 10)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition">
                                        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        <p class="text-xs text-gray-400 mt-2">JPEG or PNG, max 10MB each</p>
                                    </div>
                                    @error('photos')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                    @error('photos.*')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="checkout_notes" class="block text-sm font-medium text-gray-700 mb-1">Condition Notes (optional)</label>
                                    <textarea name="condition_notes" id="checkout_notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Note the condition of items at checkout...">{{ old('condition_notes') }}</textarea>
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700 transition">
                                    Submit Checkout Inspection
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Return Inspection Panel (shown when advancing to inspected, i.e. at checked_out or returned stage) --}}
            @if (in_array($booking->fulfilment_stage, ['checked_out', 'returned']) && !$booking->returnInspection)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-purple-50">
                        <h2 class="text-sm font-semibold text-purple-700">Return Inspection</h2>
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-500 mb-3">Upload photos and record the condition of items upon return. Flag any damage observed.</p>
                        <form method="POST" action="{{ route('admin.fulfilment.inspect', $booking) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Photos (required, max 10)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-purple-400 transition">
                                        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                                        <p class="text-xs text-gray-400 mt-2">JPEG or PNG, max 10MB each</p>
                                    </div>
                                    @error('photos')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                    @error('photos.*')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="return_notes" class="block text-sm font-medium text-gray-700 mb-1">Condition Notes (optional)</label>
                                    <textarea name="condition_notes" id="return_notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-purple-500 focus:ring-purple-500" placeholder="Note the condition of items on return...">{{ old('condition_notes') }}</textarea>
                                </div>
                                <div>
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="damage_flagged" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500" {{ old('damage_flagged') ? 'checked' : '' }}>
                                        <span class="text-sm font-medium text-gray-700">Flag damage detected</span>
                                    </label>
                                    <p class="text-xs text-gray-400 ml-6">If checked, assets will be marked as "In Repair" upon completion.</p>
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md text-sm font-medium hover:bg-purple-700 transition">
                                    Submit Return Inspection
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Inspection Gallery (shown on completed bookings with inspections) --}}
            @if ($booking->checkoutInspection || $booking->returnInspection)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Inspection Gallery</h2>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Checkout Inspection --}}
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 mb-2">Checkout Inspection</h3>
                                @if ($booking->checkoutInspection)
                                    <p class="text-xs text-gray-500 mb-2">
                                        Inspected by {{ $booking->checkoutInspection->inspector?->name ?? 'Unknown' }}
                                        on {{ $booking->checkoutInspection->inspected_at->format('d M Y H:i') }}
                                    </p>
                                    @if ($booking->checkoutInspection->condition_notes)
                                        <p class="text-sm text-gray-600 mb-3 italic">"{{ $booking->checkoutInspection->condition_notes }}"</p>
                                    @endif
                                    @if (!empty($booking->checkoutInspection->photos))
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach ($booking->checkoutInspection->photos as $photo)
                                                <img src="{{ route('admin.shop.bookings.inspectionPhoto', $photo) }}" alt="Checkout photo" class="rounded-md border object-cover h-32 w-full">
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-400">No photos recorded.</p>
                                    @endif
                                @else
                                    <p class="text-xs text-gray-400">Not yet completed.</p>
                                @endif
                            </div>

                            {{-- Return Inspection --}}
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 mb-2">Return Inspection</h3>
                                @if ($booking->returnInspection)
                                    <p class="text-xs text-gray-500 mb-2">
                                        Inspected by {{ $booking->returnInspection->inspector?->name ?? 'Unknown' }}
                                        on {{ $booking->returnInspection->inspected_at->format('d M Y H:i') }}
                                    </p>
                                    @if ($booking->returnInspection->damage_flagged)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700 mb-2">
                                            Damage Flagged
                                        </span>
                                    @endif
                                    @if ($booking->returnInspection->condition_notes)
                                        <p class="text-sm text-gray-600 mb-3 italic">"{{ $booking->returnInspection->condition_notes }}"</p>
                                    @endif
                                    @if (!empty($booking->returnInspection->photos))
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach ($booking->returnInspection->photos as $photo)
                                                <img src="{{ route('admin.shop.bookings.inspectionPhoto', $photo) }}" alt="Return photo" class="rounded-md border object-cover h-32 w-full">
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-400">No photos recorded.</p>
                                    @endif
                                @else
                                    <p class="text-xs text-gray-400">Not yet completed.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Fulfilment Timeline --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Fulfilment Timeline</h2>
                </div>
                <div class="p-4">
                    <ol class="relative border-l border-gray-200 ml-3 space-y-4">
                        @foreach ($stages as $index => $stage)
                            @php
                                $isCompleted = $index < $currentStageIndex;
                                $isCurrent = $index === $currentStageIndex;
                                $isPending = $index > $currentStageIndex;

                                $dotColor = $isCompleted ? 'bg-green-500' : ($isCurrent ? 'bg-blue-500' : 'bg-gray-300');
                                $textColor = $isCompleted ? 'text-green-700' : ($isCurrent ? 'text-blue-700 font-semibold' : 'text-gray-400');
                            @endphp
                            <li class="ml-6">
                                <span class="absolute -left-2 flex items-center justify-center w-4 h-4 rounded-full {{ $dotColor }}">
                                    @if ($isCompleted)
                                        <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    @endif
                                </span>
                                <span class="text-sm {{ $textColor }}">{{ ucwords(str_replace('_', ' ', $stage)) }}</span>
                                @if ($isCurrent)
                                    <span class="text-xs text-blue-500 block">Current</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- Advance Action --}}
            @if ($nextStage)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Next Action</h2>
                    </div>
                    <div class="p-4">
                        @if (!empty($preConditions))
                            <div class="mb-3 rounded-md bg-yellow-50 border border-yellow-200 p-3">
                                <p class="text-xs font-medium text-yellow-800 mb-1">Cannot advance yet:</p>
                                <ul class="list-disc list-inside text-xs text-yellow-700 space-y-0.5">
                                    @foreach ($preConditions as $condition)
                                        <li>{{ $condition }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @php
                            $advanceLabels = [
                                'packing' => 'Start Packing',
                                'ready' => 'Mark Ready',
                                'checked_out' => 'Check Out',
                                'returned' => 'Mark Returned',
                                'inspected' => 'Complete Inspection',
                            ];
                            $advanceLabel = $advanceLabels[$nextStage] ?? 'Advance';

                            // Don't show advance button for stages that need inspection form submission
                            $needsInspectionForm = ($nextStage === 'checked_out' && !$booking->checkoutInspection)
                                || ($nextStage === 'inspected' && !$booking->returnInspection);
                        @endphp

                        @if (!$needsInspectionForm)
                            <form method="POST" action="{{ route('admin.fulfilment.advance', $booking) }}">
                                @csrf
                                <button type="submit"
                                        @if (!empty($preConditions)) disabled @endif
                                        class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ $advanceLabel }}
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-gray-500">Complete the inspection form on the left to advance this booking.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Booking Summary --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Summary</h2>
                </div>
                <div class="p-4 space-y-3 text-sm">
                    @php
                        $days = $booking->start_date->diffInDays($booking->end_date) + 1;
                    @endphp
                    <div class="flex justify-between">
                        <span class="text-gray-500">Duration</span>
                        <span class="text-gray-900 font-medium">{{ $days }} {{ Str::plural('day', $days) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Quantity</span>
                        <span class="text-gray-900 font-medium">{{ $booking->quantity }} {{ Str::plural('unit', $booking->quantity) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status</span>
                        <span>
                            @php
                                $statusColors = [
                                    'confirmed' => 'bg-blue-100 text-blue-700',
                                    'active' => 'bg-green-100 text-green-700',
                                    'returned' => 'bg-gray-100 text-gray-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($booking->status) }}
                            </span>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Assets Assigned</span>
                        <span class="text-gray-900 font-medium">{{ $booking->assignedAssets->count() }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-3">
                        <span class="text-gray-700 font-medium">Total</span>
                        <span class="text-gray-900 font-semibold">&pound;{{ number_format($booking->total_price, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Linked Order --}}
            @if ($booking->orderItem && $booking->orderItem->order)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Linked Order</h2>
                    </div>
                    <div class="p-4 text-sm">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Order</span>
                                <a href="{{ route('admin.shop.orders.show', $booking->orderItem->order) }}" class="text-blue-600 hover:underline font-medium">
                                    #{{ $booking->orderItem->order->id }}
                                </a>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Payment</span>
                                @php
                                    $paymentColors = [
                                        'paid' => 'bg-green-100 text-green-700',
                                        'paid_offline' => 'bg-green-100 text-green-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                    ];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $paymentColors[$booking->orderItem->order->payment_status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucwords(str_replace('_', ' ', $booking->orderItem->order->payment_status)) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
