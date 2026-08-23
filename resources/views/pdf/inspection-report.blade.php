<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Inspection Report — BKG-{{ $booking->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; padding: 25px 30px; line-height: 1.4; }

        /* Header */
        .header { margin-bottom: 20px; border-bottom: 3px solid #7c3aed; padding-bottom: 12px; }
        .header-table { width: 100%; }
        .logo-cell { width: 50%; vertical-align: middle; }
        .logo-cell img { max-height: 50px; max-width: 180px; }
        .company-name { font-size: 18px; font-weight: bold; color: #1f2937; }
        .logo-text { font-size: 28px; font-weight: bold; color: #1f2937; font-family: cabin-sketch, sans-serif; letter-spacing: 0; text-transform: none; }
        .company-details { font-size: 9px; color: #6b7280; line-height: 1.3; margin-top: 3px; }
        .report-title { font-size: 20px; font-weight: bold; color: #7c3aed; text-align: right; line-height: 1.1; }
        .report-meta { text-align: right; font-size: 10px; color: #6b7280; line-height: 1.4; margin-top: 4px; }

        /* Section labels */
        .section-label { font-size: 9px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }

        /* Info boxes */
        .info-box { background-color: #f5f3ff; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; margin-bottom: 15px; }
        .info-row { font-size: 11px; color: #374151; margin-bottom: 2px; }
        .info-row strong { color: #1f2937; }

        /* Inspection section */
        .inspection-section { margin-bottom: 25px; border: 1px solid #e5e7eb; border-radius: 4px; padding: 15px; }
        .inspection-header { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; }

        /* Type badges */
        .type-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .type-checkout { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .type-return { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }

        /* Damage badge */
        .damage-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; margin-top: 6px; }
        .damage-yes { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Inspector info */
        .inspector-info { font-size: 10px; color: #6b7280; margin-top: 4px; }

        /* Notes */
        .notes-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; margin-top: 10px; margin-bottom: 10px; }
        .notes-title { font-size: 9px; font-weight: bold; color: #374151; text-transform: uppercase; margin-bottom: 4px; }
        .notes-content { font-size: 10px; color: #4b5563; line-height: 1.5; }

        /* Photos */
        .photos-section { margin-top: 10px; }
        .photos-grid { width: 100%; }
        .photo-cell { padding: 4px; vertical-align: top; text-align: center; }
        .photo-cell img { max-width: 200px; max-height: 180px; border: 1px solid #e5e7eb; border-radius: 4px; }
        .photo-label { font-size: 8px; color: #6b7280; margin-top: 2px; }

        /* Footer */
        .footer { margin-top: 25px; padding-top: 10px; border-top: 2px solid #7c3aed; text-align: center; font-size: 9px; color: #9ca3af; }

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
                    <div class="report-title">INSPECTION<br>REPORT</div>
                    <div class="report-meta">
                        <strong>Ref:</strong> BKG-{{ $booking->id }}<br>
                        <strong>Generated:</strong> {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Booking Info Table --}}
    <div class="info-box">
        <div class="section-label" style="margin-bottom: 8px;">Booking Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="info-row"><strong>Reference:</strong> BKG-{{ $booking->id }}</div>
                    <div class="info-row"><strong>Product:</strong> {{ $product->name ?? 'N/A' }}</div>
                    <div class="info-row"><strong>Customer:</strong> {{ $customer->company_name ?? $customer->customer_name ?? 'N/A' }}</div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div class="info-row"><strong>Start Date:</strong> {{ $booking->start_date->format('d/m/Y') }}</div>
                    <div class="info-row"><strong>End Date:</strong> {{ $booking->end_date->format('d/m/Y') }}</div>
                    @if($booking->returned_at)
                        <div class="info-row"><strong>Returned:</strong> {{ $booking->returned_at->format('d/m/Y') }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Multi-inspection format (from generate() method) --}}
    @if(isset($inspectionsWithPhotos) && $inspectionsWithPhotos->count() > 0)
        @foreach($inspectionsWithPhotos as $index => $entry)
            @php
                $insp = $entry['inspection'];
                $photos = $entry['photos'];
            @endphp

            @if($index > 0)
                <div class="page-break"></div>
            @endif

            <div class="inspection-section">
                <div class="inspection-header">
                    {{-- Type Badge --}}
                    <span class="type-badge {{ $insp->type === 'checkout' ? 'type-checkout' : 'type-return' }}">
                        {{ $insp->type === 'checkout' ? 'Checkout Inspection' : 'Return Inspection' }}
                    </span>

                    {{-- Damage flag warning --}}
                    @if($insp->damage_flagged)
                        <span class="damage-badge damage-yes">DAMAGE FLAGGED</span>
                    @endif

                    {{-- Inspector and timestamp --}}
                    <div class="inspector-info">
                        <strong>Inspector:</strong> {{ $insp->inspector->name ?? 'Unknown' }}
                        &nbsp;&bull;&nbsp;
                        <strong>Date:</strong> {{ $insp->inspected_at->format('d/m/Y \a\t H:i') }}
                    </div>
                </div>

                {{-- Condition Notes --}}
                @if($insp->condition_notes)
                    <div class="notes-box">
                        <div class="notes-title">Condition Notes</div>
                        <div class="notes-content">{!! nl2br(e($insp->condition_notes)) !!}</div>
                    </div>
                @endif

                {{-- Photos Grid --}}
                @if(count($photos) > 0)
                    <div class="photos-section">
                        <div class="section-label" style="margin-bottom: 6px;">Photos ({{ count($photos) }})</div>
                        <table class="photos-grid">
                            @foreach(array_chunk($photos, 3) as $row)
                                <tr>
                                    @foreach($row as $photo)
                                        <td class="photo-cell" style="width: 33.33%;">
                                            @if($photo['uri'])
                                                <img src="{{ $photo['uri'] }}" alt="Photo {{ $photo['index'] }}">
                                                <div class="photo-label">Photo {{ $photo['index'] }}</div>
                                            @else
                                                <div style="padding: 20px; background: #f3f4f6; border-radius: 4px; color: #9ca3af; font-size: 9px;">
                                                    Photo {{ $photo['index'] }} — unavailable
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                    @for($i = count($row); $i < 3; $i++)
                                        <td class="photo-cell" style="width: 33.33%;"></td>
                                    @endfor
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
        @endforeach

    {{-- Single-inspection format (from generateAndStore() method) --}}
    @elseif(isset($inspection))
        <div class="inspection-section">
            <div class="inspection-header">
                {{-- Type Badge --}}
                <span class="type-badge {{ $inspection->type === 'checkout' ? 'type-checkout' : 'type-return' }}">
                    {{ $inspection->type === 'checkout' ? 'Checkout Inspection' : 'Return Inspection' }}
                </span>

                {{-- Damage flag warning --}}
                @if($inspection->damage_flagged)
                    <span class="damage-badge damage-yes">DAMAGE FLAGGED</span>
                @endif

                {{-- Inspector and timestamp --}}
                <div class="inspector-info">
                    <strong>Inspector:</strong> {{ $inspector->name ?? 'Unknown' }}
                    &nbsp;&bull;&nbsp;
                    <strong>Date:</strong> {{ $inspection->inspected_at->format('d/m/Y \a\t H:i') }}
                </div>
            </div>

            {{-- Condition Notes --}}
            @if($inspection->condition_notes)
                <div class="notes-box">
                    <div class="notes-title">Condition Notes</div>
                    <div class="notes-content">{!! nl2br(e($inspection->condition_notes)) !!}</div>
                </div>
            @endif

            {{-- Photos Grid --}}
            @if(isset($photos) && count($photos) > 0)
                <div class="photos-section">
                    <div class="section-label" style="margin-bottom: 6px;">Photos ({{ count($photos) }})</div>
                    <table class="photos-grid">
                        @foreach(array_chunk($photos, 3) as $row)
                            <tr>
                                @foreach($row as $photo)
                                    <td class="photo-cell" style="width: 33.33%;">
                                        @if($photo['uri'])
                                            <img src="{{ $photo['uri'] }}" alt="Photo {{ $photo['index'] }}">
                                            <div class="photo-label">Photo {{ $photo['index'] }}</div>
                                        @else
                                            <div style="padding: 20px; background: #f3f4f6; border-radius: 4px; color: #9ca3af; font-size: 9px;">
                                                Photo {{ $photo['index'] }} — unavailable
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                @for($i = count($row); $i < 3; $i++)
                                    <td class="photo-cell" style="width: 33.33%;"></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        This inspection report was generated on {{ now()->format('d/m/Y \a\t H:i') }}.<br>
        {{ $companyName }} &mdash; BKG-{{ $booking->id }}
    </div>
</body>
</html>
