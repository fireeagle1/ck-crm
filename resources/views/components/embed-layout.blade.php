<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'How can we help?' }} &mdash; CK Enterprises</title>

    @php $faviconPath = \App\Models\Setting::get('favicon_path'); @endphp
    @if ($faviconPath)
        <link rel="icon" href="{{ asset($faviconPath) }}">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Transparent background so the iframe blends into the host page */
        html, body { background: transparent; }
    </style>
</head>
<body class="font-sans text-gray-900 antialiased">
    <div class="w-full px-4 py-6 sm:px-6">
        <div class="mx-auto w-full max-w-2xl">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
