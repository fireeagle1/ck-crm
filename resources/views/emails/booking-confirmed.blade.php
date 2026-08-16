<x-mail::message>
# Your Booking is Confirmed

Hi {{ $booking->customer?->customer_name ?? $booking->customer?->company_name ?? 'there' }},

Your rental booking has been confirmed. Here are the details:

**Product:** {{ $booking->product?->name ?? 'N/A' }}
**Quantity:** {{ $booking->quantity }}
**Rental Period:** {{ $booking->start_date->format('d M Y') }} — {{ $booking->end_date->format('d M Y') }}
**Duration:** {{ $booking->start_date->diffInDays($booking->end_date) }} days
**Total:** £{{ number_format($booking->total_price, 2) }}

@if ($booking->product?->delivery_instructions)
## Collection / Delivery

{{ $booking->product->delivery_instructions }}
@endif

Your booking confirmation PDF is attached to this email for your records.

**Booking Reference:** BKG-{{ $booking->id }}

@if ($booking->orderItem?->order)
<x-mail::button :url="route('portal.orders.show', $booking->orderItem->order)">
View Your Order
</x-mail::button>
@endif

If you have any questions, please don't hesitate to get in touch.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
