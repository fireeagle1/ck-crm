<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Booking Confirmation — BKG-{{ $booking->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 30px; }
        .header { margin-bottom: 25px; border-bottom: 3px solid #7c3aed; padding-bottom: 15px; }
        .header-table { width: 100%; }
        .logo-cell { width: 50%; vertical-align: middle; }
        .logo-cell img { max-height: 60px; max-width: 200px; }
        .company-name { font-size: 20px; font-weight: bold; color: #1f2937; margin: 0 0 4px; }
        .company-details { font-size: 10px; color: #6b7280; line-height: 1.4; }
        .conf-title { font-size: 22px; font-weight: bold; color: #7c3aed; text-align: right; }
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
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #7c3aed; text-align: center; font-size: 10px; color: #9ca3af; }
        .qr-section { text-align: center; margin-top: 20px; padding: 15px; border: 1px solid #e5e7eb; border-radius: 4px; background: #fafafa; }
        .qr-section img { width: 150px; height: 150px; }
        .qr-label { font-size: 10px; color: #6b7280; margin-top: 6px; font-weight: bold; }
        .qr-ref { font-size: 14px; font-weight: bold; color: #1f2937; font-family: monospace; margin-top: 4px; }
        .delivery-box { background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 12px; margin-top: 15px; }
        .delivery-title { font-size: 10px; font-weight: bold; color: #1e40af; text-transform: uppercase; margin-bottom: 5px; }
        .terms-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px; margin-top: 15px; }
        .terms-title { font-size: 10px; font-weight: bold; color: #374151; text-transform: uppercase; margin-bottom: 8px; }
        .terms-content { font-size: 9px; color: #6b7280; line-height: 1.5; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    {{-- Header with Logo --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="{{ $companyName }}">
                    @else
                        <div class="company-name">{{ $companyName }}</div>
                    @endif
                    <div class="company-details">
                        @if($companyAddress) {{ $companyAddress }}<br> @endif
                        @if($companyPhone) Tel: {{ $companyPhone }}<br> @endif
                        @if($companyEmail) {{ $companyEmail }} @endif
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div class="conf-title">BOOKING<br>CONFIRMATION</div>
                    <div class="conf-meta">
                        <strong>Ref:</strong> BKG-{{ $booking->id }}<br>
                        <strong>Date:</strong> {{ $booking->created_at->format('d/m/Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Customer Details & QR Code --}}
    <table style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="section-label">Customer</div>
                <div class="detail-block">
                    <strong>{{ $customer?->company_name ?? 'N/A' }}</strong><br>
                    @if($customer?->customer_name) {{ $customer->customer_name }}<br> @endif
                    @if($customer?->address_line1) {{ $customer->address_line1 }}<br> @endif
                    @if($customer?->city) {{ $customer->city }} {{ $customer->postal_code }}<br> @endif
                </div>

                <div style="margin-top: 12px;">
                    <div class="section-label">Booking Status</div>
                    <div class="detail-block">
                        <span class="status-badge status-{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                    </div>
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                @if($qrCodeBase64)
                    <div class="qr-section">
                        <img src="{{ $qrCodeBase64 }}" alt="QR Code">
                        <div class="qr-label">Scan to verify booking</div>
                        <div class="qr-ref">BKG-{{ $booking->id }}</div>
                    </div>
                @else
                    <div class="qr-section">
                        <div class="qr-ref">BKG-{{ $booking->id }}</div>
                        <div class="qr-label">Booking Reference</div>
                    </div>
                @endif
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

    {{-- Collection / Delivery Instructions --}}
    @if($deliveryInstructions)
        <div class="delivery-box">
            <div class="delivery-title">Collection / Delivery Information</div>
            <div style="font-size: 12px; color: #374151; line-height: 1.5;">{!! nl2br(e($deliveryInstructions)) !!}</div>
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

    {{-- Terms and Conditions --}}
    @if($rentalAgreementText)
        <div class="terms-box">
            <div class="terms-title">Terms &amp; Conditions</div>
            <div class="terms-content">{!! nl2br(e($rentalAgreementText)) !!}</div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Thank you for your booking. Please retain this confirmation for your records.<br>
        Present the QR code above at collection for quick check-in.<br>
        {{ $companyName }} &mdash; {{ $booking->created_at->format('d/m/Y') }}
    </div>
</body>
</html>
