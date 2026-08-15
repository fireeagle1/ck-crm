@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#d97706;">
        Low Stock Alert
    </h2>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        A product has fallen to or below its configured low-stock threshold and may need restocking.
    </p>

    <div style="background-color:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Product</td>
                <td style="padding:4px 0;">{{ $product->name }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Current Stock</td>
                <td style="padding:4px 0; color:#d97706; font-weight:600;">{{ $product->stock_quantity }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Low Stock Threshold</td>
                <td style="padding:4px 0;">{{ $product->low_stock_threshold }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Product Type</td>
                <td style="padding:4px 0;">{{ ucfirst(str_replace('_', ' ', $product->product_type)) }}</td>
            </tr>
        </table>
    </div>

    <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.5;">
        Please review and replenish stock as necessary. This alert will not be sent again until stock is replenished above the threshold and drops again.
    </p>
@endsection
