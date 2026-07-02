<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CK Enterprises UK') }}</title>

        <!-- Favicon -->
        @php $faviconPath = \App\Models\Setting::get('favicon_path'); @endphp
        @if ($faviconPath)
            <link rel="icon" href="{{ asset($faviconPath) }}">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

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
                        @php $logoDarkPath = \App\Models\Setting::get('logo_dark_path'); @endphp
                        @if ($logoDarkPath)
                            <img src="{{ asset($logoDarkPath) }}" alt="{{ \App\Models\Setting::get('site_name', 'CK Enterprises') }}" class="h-12 w-auto mb-4">
                        @else
                            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">CK Enterprises</h1>
                        @endif
                    </div>

                    {{ $slot }}
                </div>
            </div>

            {{-- Right: Visual panel --}}
            <div class="hidden lg:block lg:w-1/2 relative">
                <img src="https://i0.wp.com/ckenterprises.co.uk/wp-content/uploads/2023/05/DJI_0160-scaled.jpg?fit=2560%2C1440&ssl=1"
                     alt=""
                     class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0 bg-slate-900/50"></div>
                <div class="relative z-10 flex flex-col justify-end h-full p-12">
                    
                    <p class="text-white/60 text-sm mt-4 font-medium">CK Enterprises UK</p>
                </div>
            </div>
        </div>
    </body>
</html>
