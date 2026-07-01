<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CK Enterprises UK') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex">
            {{-- Left: Form panel --}}
            <div class="w-full lg:w-1/2 flex flex-col justify-center px-8 sm:px-16 xl:px-24 py-12 bg-white">
                <div class="w-full max-w-md mx-auto">
                    {{-- Brand --}}
                    <div class="mb-8">
                        @php $logoPath = \App\Models\Setting::get('logo_path'); @endphp
                        @if ($logoPath)
                            <img src="{{ asset($logoPath) }}" alt="{{ \App\Models\Setting::get('site_name', 'CK Enterprises') }}" class="h-10 w-auto mb-4">
                        @else
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">CK Enterprises</h1>
                        @endif
                        <p class="text-sm text-gray-500 mt-1">Client Management Portal</p>
                    </div>

                    {{ $slot }}
                </div>
            </div>

            {{-- Right: Visual panel --}}
            <div class="hidden lg:block lg:w-1/2 relative">
                <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80"
                     alt=""
                     class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0 bg-slate-900/60"></div>
                <div class="relative z-10 flex flex-col justify-end h-full p-12">
                    <blockquote class="text-white/90 text-lg font-medium leading-relaxed max-w-md">
                        "Managed IT services and web hosting you can rely on. Supporting businesses across the UK."
                    </blockquote>
                    <p class="text-white/60 text-sm mt-4">CK Enterprises UK</p>
                </div>
            </div>
        </div>
    </body>
</html>
