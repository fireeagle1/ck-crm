<div class="mt-6 space-y-4">
    <h4 class="font-medium text-gray-800">Equipment Inspections</h4>

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
