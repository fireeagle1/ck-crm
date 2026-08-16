@props(['value', 'size' => 150, 'label' => null])

{{-- QR Code component using Google Charts API (zero dependencies) --}}
<div class="inline-flex flex-col items-center gap-1">
    <img src="https://chart.googleapis.com/chart?chs={{ $size }}x{{ $size }}&cht=qr&chl={{ urlencode($value) }}&choe=UTF-8"
         alt="QR: {{ $value }}"
         width="{{ $size }}"
         height="{{ $size }}"
         class="border rounded">
    @if ($label)
        <span class="text-xs text-gray-500 font-mono">{{ $label }}</span>
    @endif
</div>
