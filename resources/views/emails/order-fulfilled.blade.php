@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        @if($order->delivery_method === 'collection')
            Your Order is Ready for Collection
        @else
            Your Order Has Been Dispatched
        @endif
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $order->customer?->company_name ?? 'Customer' }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        @if($order->delivery_method === 'collection')
            Great news — your order is ready to be collected from our North Manchester location.
        @else
            Great news — your order has been dispatched and is on its way to you.
        @endif
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Order Reference</td>
                <td style="padding:4px 0;">#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Order Date</td>
                <td style="padding:4px 0;">{{ $order->created_at?->format('M j, Y') }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Fulfilled</td>
                <td style="padding:4px 0;">{{ $order->fulfilled_at?->format('M j, Y H:i') }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Total</td>
                <td style="padding:4px 0;">&pound;{{ number_format($order->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($order->items && $order->items->count() > 0)
        <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
            Items in This Order
        </h3>

        <table style="width:100%; font-size:13px; color:#374151; border-collapse:collapse;">
            <tbody>
                @foreach($order->items as $item)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:8px 4px;">
                            {{ $item->product_name }}
                            @if($item->quantity > 1)
                                <span style="color:#6b7280;">&times; {{ $item->quantity }}</span>
                            @endif
                        </td>
                        <td style="padding:8px 4px; text-align:right;">
                            &pound;{{ number_format($item->price, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($order->delivery_method === 'delivery' && $order->delivery_address_line1)
        <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
            Delivery Address
        </h3>
        <div style="background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:12px; margin:0 0 16px;">
            <p style="margin:0; font-size:13px; color:#166534; line-height:1.6;">
                {{ $order->delivery_address_line1 }}<br>
                @if($order->delivery_address_line2){{ $order->delivery_address_line2 }}<br>@endif
                {{ $order->delivery_city }}@if($order->delivery_state), {{ $order->delivery_state }}@endif<br>
                {{ $order->delivery_postal_code }}<br>
                {{ $order->delivery_country }}
            </p>
        </div>
    @endif

    @if($order->delivery_method === 'collection')
        <div style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:12px; margin:0 0 16px;">
            <p style="margin:0 0 4px; font-size:13px; font-weight:600; color:#1e40af;">
                Collection Details
            </p>
            <p style="margin:0; font-size:13px; color:#1e3a5f; line-height:1.5;">
                Your order is ready to collect from our North Manchester location. Please bring a form of ID matching your account name.
            </p>
        </div>
    @endif

    <p style="margin:16px 0 0; font-size:13px; color:#6b7280; line-height:1.5;">
        If you have any questions about your order, please contact us or open a support ticket.
    </p>
@endsection
