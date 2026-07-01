<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'CK Enterprises UK' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-900 antialiased font-sans">
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
