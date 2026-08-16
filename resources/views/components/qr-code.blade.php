@props(['value', 'size' => 150, 'label' => null])

{{-- QR Code component using QuickChart.io API (free, no dependencies, no API key) --}}
<div class="inline-flex flex-col items-center gap-1">
    <img src="https://quickchart.io/qr?text={{ urlencode($value) }}&size={{ $size }}"
         alt="QR: {{ $value }}"
         width="{{ $size }}"
         height="{{ $size }}"
         class="border rounded">
    @if ($label)
        <span class="text-xs text-gray-500 font-mono">{{ $label }}</span>
    @endif
</div>
