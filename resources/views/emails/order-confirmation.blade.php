@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Order Confirmation
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $order->customer?->company_name ?? 'Customer' }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        Thank you for your order. Here are the details of your purchase:
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Order Reference</td>
                <td style="padding:4px 0;">#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Date</td>
                <td style="padding:4px 0;">{{ $order->created_at?->format('M j, Y') }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Total</td>
                <td style="padding:4px 0;">&pound;{{ number_format($order->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($order->items && $order->items->count() > 0)
        <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
            Order Items
        </h3>

        <table style="width:100%; font-size:13px; color:#374151; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <th style="padding:8px 4px; text-align:left; font-weight:600;">Product</th>
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
                            @if($item->rental_start_date && $item->rental_end_date)
                                <br><span style="font-size:12px; color:#6b7280;">
                                    {{ $item->rental_start_date->format('M j') }} &ndash; {{ $item->rental_end_date->format('M j, Y') }}
                                </span>
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

    <p style="margin:16px 0 0; font-size:13px; color:#6b7280; line-height:1.5;">
        If you have any questions about your order, please don't hesitate to contact us or open a support ticket.
    </p>
@endsection
