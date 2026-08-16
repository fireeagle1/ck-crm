<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Booking Confirmation — BKG-{{ $booking->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 30px; }
        .header { margin-bottom: 25px; border-bottom: 2px solid #7c3aed; padding-bottom: 15px; }
        .header-table { width: 100%; }
        .company-name { font-size: 20px; font-weight: bold; color: #1f2937; margin: 0 0 4px; }
        .company-details { font-size: 10px; color: #6b7280; line-height: 1.4; }
        .conf-title { font-size: 24px; font-weight: bold; color: #7c3aed; text-align: right; }
        .conf-meta { text-align: right; font-size: 11px; color: #6b7280; line-height: 1.5; }
        .section-label { font-size: 10px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .detail-block { font-size: 12px; color: #374151; line-height: 1.6; }
        .detail-block strong { color: #1f2937; }
        .info-box { background-color: #f5f3ff; border: 1px solid #e5e7eb; border-radius: 4px; padding: 15px; margin-bottom: 15px; }
        .info-row { font-size: 12px; color: #374151; margin-bottom: 4px; }
        .info-row strong { color: #1f2937; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .status-confirmed { background: #dcfce7; color: #166534; }
        .status-active { background: #dbeafe; color: #1e40af; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af; }
        .qr-section { text-align: right; margin-top: 10px; }
        .qr-label { font-size: 9px; color: #9ca3af; margin-top: 2px; }
        .delivery-box { background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 12px; margin-top: 15px; }
        .delivery-title { font-size: 10px; font-weight: bold; color: #1e40af; text-transform: uppercase; margin-bottom: 5px; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="company-details">
                        @if($companyAddress) {{ $companyAddress }}<br> @endif
                        @if($companyPhone) Tel: {{ $companyPhone }}<br> @endif
                        @if($companyEmail) {{ $companyEmail }} @endif
                    </div>
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <div class="conf-title">BOOKING<br>CONFIRMATION</div>
                    <div class="conf-meta">
                        <strong>Ref:</strong> BKG-{{ $booking->id }}<br>
                        <strong>Date:</strong> {{ $booking->created_at->format('d/m/Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Customer Details --}}
    <table style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div class="section-label">Customer</div>
                <div class="detail-block">
                    <strong>{{ $customer?->company_name ?? 'N/A' }}</strong><br>
                    @if($customer?->customer_name) {{ $customer->customer_name }}<br> @endif
                    @if($customer?->address_line1) {{ $customer->address_line1 }}<br> @endif
                    @if($customer?->city) {{ $customer->city }} {{ $customer->postal_code }}<br> @endif
                </div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div class="section-label">Booking Status</div>
                <div class="detail-block">
                    <span class="status-badge status-{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Booking Details --}}
    <div class="info-box">
        <div class="section-label" style="margin-bottom: 10px;">Rental Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">
                    <div class="info-row"><strong>Product:</strong> {{ $booking->product?->name ?? 'N/A' }}</div>
                    <div class="info-row"><strong>Quantity:</strong> {{ $booking->quantity }}</div>
                    <div class="info-row"><strong>Total Price:</strong> &pound;{{ number_format($booking->total_price, 2) }}</div>
                </td>
                <td style="width: 50%;">
                    <div class="info-row"><strong>Start Date:</strong> {{ $booking->start_date->format('d/m/Y') }}</div>
                    <div class="info-row"><strong>End Date:</strong> {{ $booking->end_date->format('d/m/Y') }}</div>
                    <div class="info-row"><strong>Duration:</strong> {{ $booking->start_date->diffInDays($booking->end_date) }} days</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Delivery Instructions --}}
    @if($deliveryInstructions)
        <div class="delivery-box">
            <div class="delivery-title">Collection / Delivery Instructions</div>
            <div style="font-size: 12px; color: #374151;">{{ $deliveryInstructions }}</div>
        </div>
    @endif

    {{-- Order Reference --}}
    @if($order)
        <div class="info-box" style="margin-top: 15px; background: #f9fafb;">
            <div class="section-label" style="margin-bottom: 8px;">Order Reference</div>
            <div class="info-row"><strong>Order #:</strong> {{ $order->id }}</div>
            <div class="info-row"><strong>Payment:</strong> {{ ucwords(str_replace('_', ' ', $order->payment_status)) }}</div>
        </div>
    @endif

    {{-- QR Code Reference --}}
    <div style="text-align: right; margin-top: 15px;">
        <div style="border: 1px solid #e5e7eb; display: inline-block; padding: 8px 12px; border-radius: 4px;">
            <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; font-weight: bold;">Booking Ref</div>
            <div style="font-size: 16px; font-weight: bold; color: #1f2937; font-family: monospace;">BKG-{{ $booking->id }}</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Thank you for your booking. Please retain this confirmation for your records.<br>
        {{ $companyName }} &mdash; {{ $booking->created_at->format('d/m/Y') }}
    </div>
</body>
</html>
