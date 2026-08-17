<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Inspection Report — BKG-{{ $booking->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 30px; }
        .header { margin-bottom: 25px; border-bottom: 3px solid #7c3aed; padding-bottom: 15px; }
        .header-table { width: 100%; }
        .logo-cell { width: 50%; vertical-align: middle; }
        .logo-cell img { max-height: 60px; max-width: 200px; }
        .company-name { font-size: 20px; font-weight: bold; color: #1f2937; margin: 0 0 4px; }
        .company-details { font-size: 10px; color: #6b7280; line-height: 1.4; }
        .report-title { font-size: 20px; font-weight: bold; color: #7c3aed; text-align: right; }
        .report-meta { text-align: right; font-size: 11px; color: #6b7280; line-height: 1.5; }
        .section-label { font-size: 10px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .info-box { background-color: #f5f3ff; border: 1px solid #e5e7eb; border-radius: 4px; padding: 15px; margin-bottom: 15px; }
        .info-row { font-size: 12px; color: #374151; margin-bottom: 4px; }
        .info-row strong { color: #1f2937; }
        .damage-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: bold; }
        .damage-yes { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .damage-no { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .notes-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px; margin-bottom: 15px; }
        .notes-title { font-size: 10px; font-weight: bold; color: #374151; text-transform: uppercase; margin-bottom: 8px; }
        .notes-content { font-size: 11px; color: #4b5563; line-height: 1.6; }
        .photos-section { margin-top: 20px; }
        .photos-grid { width: 100%; }
        .photo-cell { padding: 6px; vertical-align: top; text-align: center; }
        .photo-cell img { max-width: 100%; max-height: 220px; border: 1px solid #e5e7eb; border-radius: 4px; }
        .photo-label { font-size: 9px; color: #6b7280; margin-top: 4px; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #7c3aed; text-align: center; font-size: 10px; color: #9ca3af; }
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
                    <div class="report-title">RETURN<br>INSPECTION REPORT</div>
                    <div class="report-meta">
                        <strong>Booking:</strong> BKG-{{ $booking->id }}<br>
                        <strong>Inspected:</strong> {{ $inspection->inspected_at->format('d/m/Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Customer & Booking Details --}}
    <table style="width: 100%; margin-bottom: 15px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div class="section-label">Customer</div>
                <div class="info-row"><strong>{{ $customer?->company_name ?? 'N/A' }}</strong></div>
                @if($customer?->customer_name)
                    <div class="info-row">{{ $customer->customer_name }}</div>
                @endif
                @if($customer?->address_line1)
                    <div class="info-row">{{ $customer->address_line1 }}</div>
                @endif
                @if($customer?->city)
                    <div class="info-row">{{ $customer->city }} {{ $customer->postal_code }}</div>
                @endif
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div class="section-label">Inspector</div>
                <div class="info-row"><strong>{{ $inspector?->name ?? 'Unknown' }}</strong></div>
                <div class="info-row">{{ $inspection->inspected_at->format('d/m/Y \a\t H:i') }}</div>
            </td>
        </tr>
    </table>

    {{-- Booking Info Box --}}
    <div class="info-box">
        <div class="section-label" style="margin-bottom: 10px;">Rental Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">
                    <div class="info-row"><strong>Product:</strong> {{ $product?->name ?? 'N/A' }}</div>
                    <div class="info-row"><strong>Quantity:</strong> {{ $booking->quantity }}</div>
                    @if($order)
                        <div class="info-row"><strong>Order:</strong> #{{ $order->id }}</div>
                    @endif
                </td>
                <td style="width: 50%;">
                    <div class="info-row"><strong>Start Date:</strong> {{ $booking->start_date->format('d/m/Y') }}</div>
                    <div class="info-row"><strong>End Date:</strong> {{ $booking->end_date->format('d/m/Y') }}</div>
                    @if($booking->returned_at)
                        <div class="info-row"><strong>Returned:</strong> {{ $booking->returned_at->format('d/m/Y') }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Damage Status --}}
    <div style="margin-bottom: 15px;">
        <div class="section-label" style="margin-bottom: 8px;">Condition Assessment</div>
        <span class="damage-badge {{ $inspection->damage_flagged ? 'damage-yes' : 'damage-no' }}">
            @if($inspection->damage_flagged)
                DAMAGE REPORTED
            @else
                NO DAMAGE — GOOD CONDITION
            @endif
        </span>
    </div>

    {{-- Condition Notes --}}
    @if($inspection->condition_notes)
        <div class="notes-box">
            <div class="notes-title">Condition Notes</div>
            <div class="notes-content">{!! nl2br(e($inspection->condition_notes)) !!}</div>
        </div>
    @endif

    {{-- Photos --}}
    @if(count($photos) > 0)
        <div class="photos-section">
            <div class="section-label" style="margin-bottom: 10px;">Inspection Photos ({{ count($photos) }})</div>
            <table class="photos-grid">
                @foreach(array_chunk($photos, 2) as $row)
                    <tr>
                        @foreach($row as $photo)
                            <td class="photo-cell" style="width: 50%;">
                                @if($photo['uri'])
                                    <img src="{{ $photo['uri'] }}" alt="Photo {{ $photo['index'] }}">
                                    <div class="photo-label">Photo {{ $photo['index'] }}</div>
                                @else
                                    <div style="padding: 30px; background: #f3f4f6; border-radius: 4px; color: #9ca3af; font-size: 10px;">
                                        Photo {{ $photo['index'] }} — unavailable
                                    </div>
                                @endif
                            </td>
                        @endforeach
                        @if(count($row) === 1)
                            <td class="photo-cell" style="width: 50%;"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        This inspection report was generated automatically upon return of rented equipment.<br>
        {{ $companyName }} &mdash; {{ $inspection->inspected_at->format('d/m/Y') }}
    </div>
</body>
</html>
