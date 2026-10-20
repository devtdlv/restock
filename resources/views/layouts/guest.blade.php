@props(['title' => null])

@php
    $businessName = app(\App\Support\AppDisplay::class)->businessName();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Sign in to {{ $businessName }}, local inventory management.">
        <meta name="application-name" content="{{ $businessName }}">

        <title>{{ $title ?? ($businessName.' - Sign in') }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <a
            href="#guest-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-900 focus:shadow-lg"
        >
            Skip to main content
        </a>

        <div class="relative flex min-h-screen flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute -left-24 top-0 h-72 w-72 rounded-full bg-brand-200/40 blur-3xl"></div>
                <div class="absolute -right-16 bottom-0 h-80 w-80 rounded-full bg-slate-300/50 blur-3xl"></div>
                <div class="absolute inset-x-0 top-24 mx-auto h-px max-w-lg bg-gradient-to-r from-transparent via-brand-300/50 to-transparent"></div>
            </div>

            <div class="relative mx-auto w-full max-w-md">
                <div class="mb-8 text-center">
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex flex-col items-center gap-3 rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                        wire:navigate
                    >
                        <x-brand-mark size="lg" />
                        <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $businessName }}</span>
                    </a>
                    <p class="mt-3 text-sm text-slate-500">Local inventory management for your warehouse</p>
                </div>

                <div id="guest-content" class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
