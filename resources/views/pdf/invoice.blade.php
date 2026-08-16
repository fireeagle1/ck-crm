<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - Order #{{ $order->id }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333333;
            margin: 0;
            padding: 30px;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }
        .header-table {
            width: 100%;
        }
        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #1f2937;
            margin: 0 0 5px;
        }
        .company-details {
            font-size: 11px;
            color: #6b7280;
            line-height: 1.5;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
            text-align: right;
        }
        .invoice-meta {
            text-align: right;
            font-size: 11px;
            color: #6b7280;
            line-height: 1.6;
        }
        .details-section {
            margin-bottom: 25px;
        }
        .details-table {
            width: 100%;
        }
        .section-label {
            font-size: 10px;
            font-weight: bold;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .detail-block {
            font-size: 12px;
            color: #374151;
            line-height: 1.6;
        }
        .detail-block strong {
            color: #1f2937;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table thead th {
            background-color: #f3f4f6;
            border-bottom: 2px solid #d1d5db;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .items-table thead th.text-right {
            text-align: right;
        }
        .items-table thead th.text-center {
            text-align: center;
        }
        .items-table tbody td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            color: #374151;
            vertical-align: top;
        }
        .items-table tbody td.text-right {
            text-align: right;
        }
        .items-table tbody td.text-center {
            text-align: center;
        }
        .totals-section {
            width: 100%;
            margin-bottom: 25px;
        }
        .totals-table {
            width: 250px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 8px;
            font-size: 12px;
            color: #374151;
        }
        .totals-table td.label {
            text-align: right;
            font-weight: 600;
        }
        .totals-table td.value {
            text-align: right;
            width: 100px;
        }
        .totals-table tr.total-row td {
            border-top: 2px solid #1f2937;
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            padding-top: 10px;
        }
        .payment-info {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .payment-info-title {
            font-size: 11px;
            font-weight: bold;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .payment-info-row {
            font-size: 12px;
            color: #374151;
            margin-bottom: 4px;
        }
        .payment-info-row strong {
            color: #1f2937;
        }
        .status-paid {
            color: #059669;
            font-weight: bold;
        }
        .status-pending {
            color: #d97706;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    {{-- Header with company info and invoice title --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    @if($companyLogo)
                        <img src="{{ $companyLogo }}" alt="{{ $companyName }}" style="max-height: 50px; margin-bottom: 8px;">
                    @endif
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="company-details">
                        @if($companyAddress)
                            {{ $companyAddress }}<br>
                        @endif
                        @if($companyPhone)
                            Tel: {{ $companyPhone }}<br>
                        @endif
                        @if($companyEmail)
                            {{ $companyEmail }}
                        @endif
                    </div>
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-meta">
                        <strong>Invoice #:</strong> {{ $order->id }}<br>
                        <strong>Date:</strong> {{ $orderDate }}<br>
                        <strong>Status:</strong>
                        <span class="{{ in_array($order->payment_status, ['paid', 'paid_offline']) ? 'status-paid' : 'status-pending' }}">
                            {{ $paymentStatus }}
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Customer and delivery details --}}
    <div class="details-section">
        <table class="details-table">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="section-label">Bill To</div>
                    <div class="detail-block">
                        @if($customer)
                            <strong>{{ $customer->company_name }}</strong><br>
                            @if($customer->customer_name)
                                {{ $customer->customer_name }}<br>
                            @endif
                            @if($customer->address_line1)
                                {{ $customer->address_line1 }}<br>
                            @endif
                            @if($customer->address_line2)
                                {{ $customer->address_line2 }}<br>
                            @endif
                            @if($customer->city || $customer->postal_code)
                                {{ $customer->city }}{{ $customer->city && $customer->postal_code ? ', ' : '' }}{{ $customer->postal_code }}<br>
                            @endif
                            @if($customer->country)
                                {{ $customer->country }}
                            @endif
                        @else
                            N/A
                        @endif
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    @if($deliveryAddress)
                        <div class="section-label">Deliver To</div>
                        <div class="detail-block">
                            @if($order->delivery_address_line1)
                                {{ $order->delivery_address_line1 }}<br>
                            @endif
                            @if($order->delivery_address_line2)
                                {{ $order->delivery_address_line2 }}<br>
                            @endif
                            @if($order->delivery_city || $order->delivery_postal_code)
                                {{ $order->delivery_city }}{{ $order->delivery_city && $order->delivery_postal_code ? ', ' : '' }}{{ $order->delivery_postal_code }}<br>
                            @endif
                            @if($order->delivery_state)
                                {{ $order->delivery_state }}<br>
                            @endif
                            @if($order->delivery_country)
                                {{ $order->delivery_country }}
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Line items table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Product</th>
                <th class="text-center" style="width: 15%;">Qty</th>
                <th class="text-right" style="width: 20%;">Unit Price</th>
                <th class="text-right" style="width: 20%;">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}
                        @if($item->domain_name)
                            <br><small style="color: #6b7280;">Domain: {{ $item->domain_name }}</small>
                        @endif
                        @if($item->rental_start_date && $item->rental_end_date)
                            <br><small style="color: #6b7280;">Rental: {{ $item->rental_start_date->format('d/m/Y') }} - {{ $item->rental_end_date->format('d/m/Y') }}</small>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->quantity ?? 1 }}</td>
                    <td class="text-right">&pound;{{ number_format($item->price, 2) }}</td>
                    <td class="text-right">&pound;{{ number_format($item->price * ($item->quantity ?? 1), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="label">Subtotal:</td>
                <td class="value">&pound;{{ number_format($subtotal, 2) }}</td>
            </tr>
            @if($vatRate > 0)
                <tr>
                    <td class="label">VAT ({{ number_format($vatRate, 0) }}%):</td>
                    <td class="value">&pound;{{ number_format($vatAmount, 2) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="label">Total:</td>
                <td class="value">&pound;{{ number_format($total, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Payment information --}}
    <div class="payment-info">
        <div class="payment-info-title">Payment Information</div>
        <div class="payment-info-row">
            <strong>Payment Status:</strong>
            <span class="{{ in_array($order->payment_status, ['paid', 'paid_offline']) ? 'status-paid' : 'status-pending' }}">
                {{ $paymentStatus }}
            </span>
        </div>
        <div class="payment-info-row">
            <strong>Payment Reference:</strong> {{ $paymentReference }}
        </div>
        <div style="margin-top: 10px; text-align: right;">
            <div style="border: 1px solid #e5e7eb; display: inline-block; padding: 6px 10px; border-radius: 4px;">
                <span style="font-size: 9px; color: #6b7280; text-transform: uppercase; font-weight: bold;">Ref: </span>
                <span style="font-size: 12px; font-weight: bold; font-family: monospace; color: #1f2937;">ORD-{{ $order->id }}</span>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Thank you for your business.<br>
        {{ $companyName }} &mdash; {{ $orderDate }}
    </div>
</body>
</html>
