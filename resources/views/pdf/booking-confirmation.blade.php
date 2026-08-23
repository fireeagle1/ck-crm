<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Booking Confirmation — BKG-{{ $booking->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; padding: 25px 30px; line-height: 1.4; }

        /* Header */
        .header { margin-bottom: 20px; border-bottom: 3px solid #7c3aed; padding-bottom: 12px; }
        .header-table { width: 100%; }
        .logo-cell { width: 50%; vertical-align: middle; }
        .logo-cell img { max-height: 50px; max-width: 180px; }
        .company-name { font-size: 18px; font-weight: bold; color: #1f2937; }
        .logo-text { font-size: 22px; font-weight: bold; color: #1f2937; font-family: 'Courier New', Courier, monospace; letter-spacing: 1px; text-transform: uppercase; }
        .company-details { font-size: 9px; color: #6b7280; line-height: 1.3; margin-top: 3px; }
        .conf-title { font-size: 20px; font-weight: bold; color: #7c3aed; text-align: right; line-height: 1.1; }
        .conf-meta { text-align: right; font-size: 10px; color: #6b7280; line-height: 1.4; margin-top: 4px; }

        /* Sections */
        .section-label { font-size: 9px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .detail-block { font-size: 11px; color: #374151; line-height: 1.5; }
        .detail-block strong { color: #1f2937; }

        /* Info boxes */
        .info-box { background-color: #f5f3ff; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; margin-bottom: 12px; }
        .info-row { font-size: 11px; color: #374151; margin-bottom: 2px; }
        .info-row strong { color: #1f2937; }

        /* Status */
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .status-confirmed { background: #dcfce7; color: #166534; }
        .status-active { background: #dbeafe; color: #1e40af; }

        /* QR Code */
        .qr-section { text-align: center; padding: 10px; border: 1px solid #e5e7eb; border-radius: 4px; background: #fafafa; }
        .qr-section img { width: 120px; height: 120px; }
        .qr-label { font-size: 8px; color: #6b7280; margin-top: 4px; }
        .qr-ref { font-size: 12px; font-weight: bold; color: #1f2937; font-family: monospace; margin-top: 2px; }

        /* Delivery box */
        .delivery-box { background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 10px 12px; margin-bottom: 12px; }
        .delivery-title { font-size: 9px; font-weight: bold; color: #1e40af; text-transform: uppercase; margin-bottom: 4px; }

        /* Terms */
        .terms-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px; margin-top: 12px; }
        .terms-title { font-size: 9px; font-weight: bold; color: #374151; text-transform: uppercase; margin-bottom: 6px; }
        .terms-content { font-size: 8px; color: #4b5563; line-height: 1.4; }
        .terms-content p { margin-bottom: 4px; }
        .terms-content h2, .terms-content h3 { font-size: 9px; font-weight: bold; color: #1f2937; margin-top: 8px; margin-bottom: 3px; }

        /* Footer */
        .footer { margin-top: 20px; padding-top: 10px; border-top: 2px solid #7c3aed; text-align: center; font-size: 9px; color: #9ca3af; }

        /* Page break */
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    {{-- Header with Logo --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <div class="logo-text">CK Enterprises UK</div>
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
    <table style="width: 100%; margin-bottom: 15px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="section-label">Customer</div>
                <div class="detail-block">
                    <strong>{{ $customer?->company_name ?? 'N/A' }}</strong><br>
                    @if($customer?->customer_name) {{ $customer->customer_name }}<br> @endif
                    @if($customer?->address_line1) {{ $customer->address_line1 }}<br> @endif
                    @if($customer?->city) {{ $customer->city }} {{ $customer->postal_code }}<br> @endif
                </div>
                <div style="margin-top: 8px;">
                    <div class="section-label">Booking Status</div>
                    <span class="status-badge status-{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
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
        <div class="section-label" style="margin-bottom: 6px;">Rental Details</div>
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
            <div style="font-size: 11px; color: #374151; line-height: 1.4;">{!! nl2br(e($deliveryInstructions)) !!}</div>
        </div>
    @endif

    {{-- Order Reference --}}
    @if($order)
        <div class="info-box" style="background: #f9fafb;">
            <div class="section-label" style="margin-bottom: 4px;">Order Reference</div>
            <div class="info-row"><strong>Order #:</strong> {{ $order->id }}</div>
            <div class="info-row"><strong>Payment:</strong> {{ ucwords(str_replace('_', ' ', $order->payment_status)) }}</div>
        </div>
    @endif

    {{-- Terms and Conditions (rendered as actual HTML, on new page if present) --}}
    @if($rentalAgreementText)
        <div class="page-break"></div>
        <div class="terms-box">
            <div class="terms-title">Terms &amp; Conditions</div>
            <div class="terms-content">{!! clean($rentalAgreementText) !!}</div>
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
