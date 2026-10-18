@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'StockPilot') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <div class="relative flex min-h-screen flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute -left-24 top-0 h-72 w-72 rounded-full bg-brand-200/40 blur-3xl"></div>
                <div class="absolute -right-16 bottom-0 h-80 w-80 rounded-full bg-slate-300/50 blur-3xl"></div>
            </div>

            <div class="relative mx-auto w-full max-w-md">
                <div class="mb-8 text-center">
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-700 text-lg font-bold text-white shadow-sm">
                            SP
                        </span>
                        <span class="text-2xl font-bold tracking-tight text-slate-900">StockPilot</span>
                    </a>
                    <p class="mt-3 text-sm text-slate-500">Local inventory management</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
