<x-mail::message>
# Your Rental Ends Tomorrow

Hi {{ $booking->customer?->customer_name ?? $booking->customer?->company_name ?? 'there' }},

This is a friendly reminder that your rental is due back **tomorrow**.

**Product:** {{ $booking->product?->name ?? 'N/A' }}
**Quantity:** {{ $booking->quantity }}
**End Date:** {{ $booking->end_date->format('d M Y') }}
**Booking Ref:** BKG-{{ $booking->id }}

@if ($booking->product?->delivery_instructions)
## Return / Collection Instructions

{{ $booking->product->delivery_instructions }}
@endif

Please ensure the equipment is returned by the end date to avoid any additional charges.

@if ($booking->orderItem?->order)
<x-mail::button :url="route('portal.orders.show', $booking->orderItem->order)">
View Your Booking
</x-mail::button>
@endif

If you need to extend your rental or have any questions, please get in touch.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
