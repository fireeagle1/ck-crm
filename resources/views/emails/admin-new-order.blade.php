@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        New Order Received
    </h2>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        A new order has been placed and requires attention.
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Order ID</td>
                <td style="padding:4px 0;">#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Customer</td>
                <td style="padding:4px 0;">{{ $order->customer?->company_name ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Total</td>
                <td style="padding:4px 0;">&pound;{{ number_format($order->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Payment Status</td>
                <td style="padding:4px 0;">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Date</td>
                <td style="padding:4px 0;">{{ $order->created_at?->format('M j, Y \a\t H:i') }}</td>
            </tr>
        </table>
    </div>

    @if($order->items && $order->items->count() > 0)
        <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
            Items Ordered
        </h3>

        <table style="width:100%; font-size:13px; color:#374151; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <th style="padding:8px 4px; text-align:left; font-weight:600;">Product</th>
                    <th style="padding:8px 4px; text-align:left; font-weight:600;">Type</th>
                    <th style="padding:8px 4px; text-align:right; font-weight:600;">Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:8px 4px;">
                            {{ $item->product?->name ?? 'Product' }}
                            @if($item->quantity > 1)
                                <span style="color:#6b7280;">&times; {{ $item->quantity }}</span>
                            @endif
                        </td>
                        <td style="padding:8px 4px;">
                            {{ ucfirst(str_replace('_', ' ', $item->product?->product_type ?? '')) }}
                        </td>
                        <td style="padding:8px 4px; text-align:right;">
                            &pound;{{ number_format($item->price, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($order->items && $order->items->contains(fn($item) => $item->product?->delivery_instructions))
        <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
            Delivery / Collection Instructions
        </h3>

        @foreach($order->items as $item)
            @if($item->product?->delivery_instructions)
                <div style="background-color:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:12px; margin:0 0 8px;">
                    <p style="margin:0 0 4px; font-size:13px; font-weight:600; color:#92400e;">
                        {{ $item->product->name }}
                    </p>
                    <p style="margin:0; font-size:13px; color:#78350f; line-height:1.5;">
                        {{ $item->product->delivery_instructions }}
                    </p>
                </div>
            @endif
        @endforeach
    @endif

    @if($order->delivery_address_line1)
        <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
            Delivery Address
        </h3>

        <p style="margin:0; font-size:13px; color:#374151; line-height:1.6;">
            {{ $order->delivery_address_line1 }}<br>
            @if($order->delivery_address_line2){{ $order->delivery_address_line2 }}<br>@endif
            {{ $order->delivery_city }}@if($order->delivery_state), {{ $order->delivery_state }}@endif<br>
            {{ $order->delivery_postal_code }}<br>
            {{ $order->delivery_country }}
        </p>
    @endif
@endsection
