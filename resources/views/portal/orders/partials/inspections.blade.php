<div class="mt-6">
    <div class="flex items-center gap-2 mb-4">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <h4 class="font-semibold text-gray-800">Equipment Inspections</h4>
    </div>

    <div class="space-y-4">
        @if($booking->checkoutInspection)
            @include('portal.orders.partials.inspection-card', [
                'inspection' => $booking->checkoutInspection,
                'title' => 'Checkout Inspection'
            ])
        @endif

        @if($booking->returnInspection)
            @include('portal.orders.partials.inspection-card', [
                'inspection' => $booking->returnInspection,
                'title' => 'Return Inspection'
            ])
        @endif
    </div>
</div>
