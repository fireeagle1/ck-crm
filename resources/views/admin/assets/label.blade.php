<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Asset Label — CMDB-{{ $asset->device_id }}</title>
    <style>
        @media print {
            @page { margin: 10mm; size: 62mm 29mm; } /* Standard label size */
            body { margin: 0; }
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f9fafb;
        }
        .label {
            border: 2px solid #1f2937;
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            background: white;
            max-width: 400px;
        }
        .label-info {
            flex: 1;
        }
        .label-id {
            font-size: 14px;
            font-weight: 700;
            font-family: monospace;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .label-name {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 2px;
        }
        .label-serial {
            font-size: 11px;
            color: #6b7280;
            font-family: monospace;
        }
        .label-type {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 2px;
        }
        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .print-btn:hover { background: #1d4ed8; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <div class="label">
        <img src="https://quickchart.io/qr?text={{ urlencode('CMDB-' . $asset->device_id) }}&size=100"
             width="100" height="100" alt="QR: CMDB-{{ $asset->device_id }}">
        <div class="label-info">
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

    <button class="print-btn" onclick="window.print()">Print Label</button>
</body>
</html>
