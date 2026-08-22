<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Asset Label — CMDB-{{ $asset->device_id }}</title>
    <style>
        :root {
            --label-width: 62mm;
            --label-height: {{ $size === 'compact' ? '30mm' : '40mm' }};
            --qr-size: {{ $size === 'compact' ? '18mm' : '24mm' }};
            --logo-height: {{ $size === 'compact' ? '6mm' : '8mm' }};
            --font-id: {{ $size === 'compact' ? '9pt' : '11pt' }};
            --font-name: {{ $size === 'compact' ? '8pt' : '9pt' }};
            --font-serial: {{ $size === 'compact' ? '6.5pt' : '7.5pt' }};
            --font-support: {{ $size === 'compact' ? '6pt' : '7pt' }};
            --padding: {{ $size === 'compact' ? '2mm' : '3mm' }};
            --gap: {{ $size === 'compact' ? '2mm' : '3mm' }};
        }

        @media print {
            @page {
                margin: 0;
                size: var(--label-width) var(--label-height);
            }
            body { margin: 0; background: white; }
            .no-print { display: none !important; }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f3f4f6;
        }

        .label {
            width: var(--label-width);
            height: var(--label-height);
            padding: var(--padding);
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid #d1d5db;
            overflow: hidden;
        }

        .label-top {
            display: flex;
            align-items: flex-start;
            gap: var(--gap);
        }

        .label-logo {
            height: var(--logo-height);
            width: auto;
            max-width: 20mm;
            object-fit: contain;
        }

        .label-qr {
            width: var(--qr-size);
            height: var(--qr-size);
            flex-shrink: 0;
        }

        .label-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .label-id {
            font-size: var(--font-id);
            font-weight: 700;
            font-family: 'Courier New', monospace;
            color: #111827;
            line-height: 1.2;
        }

        .label-name {
            font-size: var(--font-name);
            font-weight: 600;
            color: #374151;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-serial {
            font-size: var(--font-serial);
            color: #6b7280;
            font-family: 'Courier New', monospace;
            line-height: 1.3;
        }

        .label-type {
            font-size: var(--font-serial);
            color: #9ca3af;
            line-height: 1.3;
        }

        .label-footer {
            border-top: 0.3pt solid #e5e7eb;
            padding-top: 1mm;
            text-align: center;
        }

        .label-support {
            font-size: var(--font-support);
            color: #6b7280;
        }

        /* Controls (hidden when printing) */
        .controls {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover { background: #1d4ed8; }

        .btn-outline {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-outline:hover { background: #f9fafb; }

        .size-toggle {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 13px;
            color: #4b5563;
        }

        .size-toggle a {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            color: #374151;
            border: 1px solid #d1d5db;
            font-weight: 500;
        }
        .size-toggle a.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }
    </style>
</head>
<body>
    {{-- Label Preview / Print Area --}}
    <div class="label">
        <div class="label-top">
            {{-- QR Code --}}
            <img class="label-qr"
                 src="https://quickchart.io/qr?text={{ urlencode('CMDB-' . $asset->device_id) }}&size=200&margin=0"
                 alt="QR: CMDB-{{ $asset->device_id }}">

            {{-- Asset Info --}}
            <div class="label-info">
                {{-- Logo --}}
                @php
                    $logoPath = \App\Models\Setting::get('logo_dark_path') ?? \App\Models\Setting::get('logo_path');
                @endphp
                @if ($logoPath)
                    <img class="label-logo" src="{{ asset($logoPath) }}" alt="CK Enterprises">
                @else
                    <span style="font-size: var(--font-name); font-weight: 700; color: #111827;">CK Enterprises</span>
                @endif

                <div class="label-id">CMDB-{{ $asset->device_id }}</div>
                <div class="label-name">{{ $asset->device_name }}</div>
                @if ($asset->serial_number)
                    <div class="label-serial">S/N: {{ $asset->serial_number }}</div>
                @endif
                @if ($asset->device_type)
                    <div class="label-type">{{ $asset->device_type }}</div>
                @endif
            </div>
        </div>

        <div class="label-footer">
            <div class="label-support">For support contact CKEnterprises.co.uk</div>
        </div>
    </div>

    {{-- Controls (not printed) --}}
    <div class="controls no-print">
        <a href="{{ route('admin.assets.label-download', ['asset' => $asset->device_id, 'size' => $size]) }}" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
            </svg>
            Download Label (PNG)
        </a>

        <button class="btn btn-outline" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 10a1 1 0 0 1-1-1v-2h8v2a1 1 0 0 1-1 1H5z"/>
            </svg>
            Print Label
        </button>

        <div class="size-toggle">
            <span>Size:</span>
            <a href="{{ route('admin.assets.label', ['asset' => $asset->device_id, 'size' => 'standard']) }}"
               class="{{ $size === 'standard' ? 'active' : '' }}">Standard</a>
            <a href="{{ route('admin.assets.label', ['asset' => $asset->device_id, 'size' => 'compact']) }}"
               class="{{ $size === 'compact' ? 'active' : '' }}">Compact</a>
        </div>

        <a href="{{ route('admin.assets.show', $asset) }}" class="btn btn-outline">&larr; Back to Asset</a>
    </div>
</body>
</html>
