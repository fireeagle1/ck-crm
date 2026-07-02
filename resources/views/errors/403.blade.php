<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied - CK Enterprises</title>
    @php $faviconPath = \App\Models\Setting::get('favicon_path'); @endphp
    @if ($faviconPath)
        <link rel="icon" href="{{ asset($faviconPath) }}">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-white antialiased font-sans min-h-screen flex flex-col">
    {{-- Header --}}
    <header class="py-6 px-6">
        <div class="max-w-7xl mx-auto">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                @php $logoPath = \App\Models\Setting::get('logo_path'); @endphp
                @if ($logoPath)
                    <img src="{{ asset($logoPath) }}" alt="CK Enterprises" class="h-10 w-auto">
                @else
                    <span class="text-xl font-bold text-white tracking-tight">CK Enterprises</span>
                @endif
            </a>
        </div>
    </header>

    {{-- Content --}}
    <main class="flex-1 flex items-center justify-center px-6">
        <div class="text-center max-w-lg">
            <p class="text-red-400 text-sm font-semibold uppercase tracking-wider mb-3">Error 403</p>
            <h1 class="text-5xl sm:text-6xl font-extrabold mb-4">Access denied</h1>
            <p class="text-gray-400 text-lg mb-8">
                {{ $exception->getMessage() ?: "You don't have permission to access this page." }}
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ url('/') }}"
                   class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition shadow-lg shadow-blue-600/20">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Go Home
                </a>
                <a href="{{ route('portal.tickets.create') }}"
                   class="inline-flex items-center px-5 py-2.5 border border-white/20 text-white rounded-lg text-sm font-semibold hover:bg-white/10 transition">
                    Contact Support
                </a>
            </div>
        </div>
    </main>

    {{-- Footer --}}
    <footer class="py-6 px-6 text-center">
        <p class="text-gray-500 text-sm">&copy; {{ date('Y') }} CK Enterprises UK. All rights reserved.</p>
    </footer>
</body>
</html>
