<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'CK Enterprises UK' }}</title>
    @php $faviconPath = \App\Models\Setting::get('favicon_path'); @endphp
    @if ($faviconPath)
        <link rel="icon" href="{{ asset($faviconPath) }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900 antialiased">
    @include('layouts.partials.portal-header')

    @if (session('success'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm text-red-700">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <main class="max-w-7xl mx-auto px-4 py-6">
        {{ $slot }}
    </main>
</body>
</html>
