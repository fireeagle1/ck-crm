{{-- Booking details partial: dates, status, timeline, download confirmation --}}
{{-- Receives: $booking --}}
@php
    $customerStageLabels = \App\View\Components\FulfilmentTimeline::CUSTOMER_STAGE_LABELS;
@endphp

<div class="mt-4 space-y-4">
    {{-- Dates and Status --}}
    <div class="flex flex-col sm:flex-row gap-4">
        <div>
            <span class="text-sm text-gray-500">Start Date</span>
            <p class="font-medium">{{ $booking->start_date->format('d M Y') }}</p>
        </div>
        <div>
            <span class="text-sm text-gray-500">End Date</span>
            <p class="font-medium">{{ $booking->end_date->format('d M Y') }}</p>
        </div>
        <div>
            <span class="text-sm text-gray-500">Status</span>
            <p class="font-medium">{{ $customerStageLabels[$booking->fulfilment_stage] ?? ucfirst(str_replace('_', ' ', $booking->fulfilment_stage)) }}</p>
        </div>
    </div>

    {{-- Booking Timeline --}}
    <x-fulfilment-timeline
        :current-stage="$booking->fulfilment_stage"
        :labels="$customerStageLabels"
        layout="responsive"
    />

    {{-- Download Confirmation --}}
    <a href="{{ route('portal.orders.downloadBookingConfirmation', $booking) }}"
       class="inline-flex items-center min-w-[44px] min-h-[44px] px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium">
        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Download Confirmation
    </a>

    {{-- Inspection Reports --}}
    @if($booking->checkoutInspection || $booking->returnInspection)
        @include('portal.orders.partials.inspections', ['booking' => $booking])
    @endif
</div>
