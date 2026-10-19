@props(['title' => null, 'header' => 'Workspace'])

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
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased" x-data="{ sidebarOpen: false }">
        <div class="flex min-h-screen">
            {{-- Mobile overlay --}}
            <div
                x-show="sidebarOpen"
                x-transition.opacity
                class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden"
                @click="sidebarOpen = false"
                style="display: none;"
            ></div>

            {{-- Sidebar --}}
            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-100 transition-transform duration-200 ease-out lg:static lg:translate-x-0"
                :class="{ 'translate-x-0': sidebarOpen }"
            >
                <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500 text-sm font-bold text-white">
                        SP
                    </span>
                    <div>
                        <p class="text-sm font-semibold tracking-tight">StockPilot</p>
                        <p class="text-xs text-slate-400">Inventory</p>
                    </div>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
                    @php
                        $navItems = [
                            ['label' => 'Dashboard', 'route' => 'dashboard', 'ready' => true],
                            ['label' => 'Products', 'route' => 'products.index', 'ready' => true],
                            ['label' => 'Categories', 'route' => 'categories.index', 'ready' => true],
                            ['label' => 'Suppliers', 'route' => null, 'ready' => false],
                            ['label' => 'Stock Movements', 'route' => null, 'ready' => false],
                            ['label' => 'Purchase Orders', 'route' => null, 'ready' => false],
                            ['label' => 'Reports', 'route' => null, 'ready' => false],
                            ['label' => 'Settings', 'route' => null, 'ready' => false],
                        ];
                    @endphp

                    @foreach ($navItems as $item)
                        @if ($item['ready'])
                            <a
                                href="{{ route($item['route']) }}"
                                @click="sidebarOpen = false"
                                @class([
                                    'flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition',
                                    request()->routeIs($item['route'])
                                        ? 'bg-white/10 text-white'
                                        : 'text-slate-300 hover:bg-white/5 hover:text-white',
                                ])
                            >
                                {{ $item['label'] }}
                            </a>
                        @else
                            <span
                                class="flex cursor-not-allowed items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium text-slate-500"
                                title="Coming soon"
                            >
                                <span>{{ $item['label'] }}</span>
                                <span class="text-[10px] uppercase tracking-wide text-slate-600">Soon</span>
                            </span>
                        @endif
                    @endforeach
                </nav>

                <div class="border-t border-white/10 p-4 text-xs text-slate-500">
                    Local-only workspace
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Top bar --}}
                <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 lg:hidden"
                            @click="sidebarOpen = !sidebarOpen"
                            aria-label="Toggle navigation"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div class="hidden sm:block">
                            <p class="text-sm font-semibold text-slate-900">{{ $header ?? 'Workspace' }}</p>
                            <p class="text-xs text-slate-500">{{ config('app.name', 'StockPilot') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="text-sm font-medium text-slate-900">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ Auth::user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                Log out
                            </button>
                        </form>
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
