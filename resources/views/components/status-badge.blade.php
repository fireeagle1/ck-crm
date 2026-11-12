@props([
    // Raw status value (null is valid for the provisioning type => "Not tracked").
    'status' => null,
    // One of: service, provisioning, domain-sync.
    'type' => 'service',
    // Tailwind size classes; the index tables use px-2, the show page px-2.5.
    'size' => 'px-2 py-0.5',
])

@php
    $green = 'bg-green-100 text-green-700';
    $red = 'bg-red-100 text-red-700';
    $amber = 'bg-amber-100 text-amber-700';
    $gray = 'bg-gray-100 text-gray-600';
    $blue = 'bg-blue-100 text-blue-700';

    $key = $status instanceof \BackedEnum ? $status->value : $status;

    $maps = [
        'service' => [
            'prefix' => 'Status',
            'items' => [
                'Pending' => ['Pending', $amber],
                'Active' => ['Active', $green],
                'Suspended' => ['Suspended', $red],
                'Cancelled' => ['Cancelled', $red],
            ],
            'fallback' => $gray,
        ],
        'provisioning' => [
            'prefix' => 'Provisioning status',
            'items' => [
                'queued' => ['Queued', $blue],
                'running' => ['Running', $amber],
                'succeeded' => ['Succeeded', $green],
                'failed' => ['Failed', $red],
            ],
            'fallback' => $gray,
        ],
        'domain-sync' => [
            'prefix' => 'Sync status',
            'items' => [
                'active' => ['Active', $green],
                'missing_from_enom' => ['Missing from eNom', $amber],
            ],
            'fallback' => $gray,
        ],
    ];

    $map = $maps[$type] ?? $maps['service'];

    if ($key === null || $key === '') {
        [$label, $colour] = ['Not tracked', $gray];
    } elseif (isset($map['items'][$key])) {
        [$label, $colour] = $map['items'][$key];
    } else {
        [$label, $colour] = [ucfirst(str_replace('_', ' ', (string) $key)), $map['fallback']];
    }
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full {$size} text-xs font-medium {$colour}"]) }}><span class="sr-only">{{ $map['prefix'] }}: </span>{{ $label }}</span>
