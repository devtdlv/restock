@props(['title' => null, 'header' => 'Workspace'])

@php
    $appDisplay = app(\App\Support\AppDisplay::class);
    $businessName = $appDisplay->businessName();
    $user = Auth::user();
    $nameParts = preg_split('/\s+/', trim((string) $user->name));
    $initials = collect(is_array($nameParts) ? $nameParts : [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr((string) $user->email, 0, 1));
    }

    $navGroups = [
        [
            'label' => 'Overview',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home'],
            ],
        ],
        [
            'label' => 'Inventory',
            'items' => [
                ['label' => 'Products', 'route' => 'products.index', 'icon' => 'cube'],
                ['label' => 'Categories', 'route' => 'categories.index', 'icon' => 'tag'],
                ['label' => 'Stock Movements', 'route' => 'stock-movements.index', 'icon' => 'arrows'],
            ],
        ],
        [
            'label' => 'Purchasing',
            'items' => [
                ['label' => 'Suppliers', 'route' => 'suppliers.index', 'icon' => 'building'],
                [
                    'label' => 'Purchase Orders',
                    'route' => 'purchase-orders.index',
                    'active' => 'purchase-orders.*',
                    'icon' => 'clipboard',
                ],
            ],
        ],
        [
            'label' => 'Insights',
            'items' => [
                ['label' => 'Reports', 'route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'chart'],
                ['label' => 'Audit Logs', 'route' => 'audit-logs.index', 'icon' => 'document'],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['label' => 'Settings', 'route' => 'settings.index', 'icon' => 'cog'],
            ],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ $businessName }} local inventory management workspace.">
        <meta name="application-name" content="{{ $businessName }}">

        <title>{{ $title ?? ($businessName.' - Inventory') }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased"
        x-data="{
            sidebarOpen: false,
            isDesktop: false,
            init() {
                const media = window.matchMedia('(min-width: 1024px)');
                const sync = () => {
                    this.isDesktop = media.matches;
                    if (media.matches) {
                        this.sidebarOpen = false;
                    }
                };
                sync();
                media.addEventListener('change', sync);
            },
            get navDrawerHidden() {
                return ! this.isDesktop && ! this.sidebarOpen;
            },
            openSidebar() {
                this.sidebarOpen = true;
                this.$nextTick(() => this.$refs.firstNavLink?.focus());
            },
            closeSidebar() {
                if (! this.sidebarOpen) {
                    return;
                }
                this.sidebarOpen = false;
                this.$nextTick(() => this.$refs.navToggle?.focus());
            },
            toggleSidebar() {
                this.sidebarOpen ? this.closeSidebar() : this.openSidebar();
            },
        }"
        :class="{ 'overflow-hidden lg:overflow-auto': sidebarOpen }"
        @keydown.escape.window="closeSidebar()"
    >
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-900 focus:shadow-lg"
        >
            Skip to main content
        </a>

        <div class="flex min-h-screen overflow-x-clip">
            <div
                x-show="sidebarOpen"
                x-transition.opacity
                class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden"
                @click="closeSidebar()"
                x-cloak
            ></div>

            <aside
                id="app-sidebar"
                class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-white/10 bg-slate-900 text-slate-100 transition-transform duration-200 ease-out lg:static lg:translate-x-0 lg:visible"
                :class="{ 'translate-x-0': sidebarOpen, 'max-lg:invisible': navDrawerHidden }"
                :aria-hidden="navDrawerHidden.toString()"
                aria-label="Application"
            >
                <div class="flex h-16 items-center border-b border-white/10 px-4">
                    <a
                        href="{{ route('dashboard') }}"
                        class="rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-300"
                        wire:navigate
                        @click="closeSidebar()"
                    >
                        <x-brand-wordmark class="text-white" subtitle="Inventory" />
                    </a>
                </div>

                <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4" aria-label="Main">
                    @foreach ($navGroups as $groupIndex => $group)
                        <div>
                            <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                {{ $group['label'] }}
                            </p>
                            <div class="space-y-0.5">
                                @foreach ($group['items'] as $itemIndex => $item)
                                    @if ($groupIndex === 0 && $itemIndex === 0)
                                        <x-nav-item
                                            :label="$item['label']"
                                            :route="$item['route']"
                                            :active="$item['active'] ?? null"
                                            :icon="$item['icon']"
                                            x-ref="firstNavLink"
                                        />
                                    @else
                                        <x-nav-item
                                            :label="$item['label']"
                                            :route="$item['route']"
                                            :active="$item['active'] ?? null"
                                            :icon="$item['icon']"
                                        />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="border-t border-white/10 p-4 text-xs text-slate-500">
                    Local-only {{ $businessName }} workspace
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur">
                    <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                x-ref="navToggle"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 lg:hidden"
                                @click="toggleSidebar()"
                                :aria-expanded="sidebarOpen.toString()"
                                aria-controls="app-sidebar"
                                aria-label="Toggle navigation"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>

                            {{-- Mobile: current page title. Desktop: business context only (page h1 lives in main). --}}
                            <div class="min-w-0 lg:hidden">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $header ?? 'Workspace' }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $businessName }}</p>
                            </div>
                            <div class="hidden min-w-0 lg:block">
                                <p class="truncate text-sm font-medium text-slate-600">{{ $businessName }}</p>
                                <p class="truncate text-xs text-slate-400">Local inventory workspace</p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800 sm:hidden"
                                aria-hidden="true"
                                title="{{ $user->name }}"
                            >
                                {{ $initials }}
                            </div>
                            <div class="hidden text-right sm:block">
                                <p class="text-sm font-medium text-slate-900">{{ $user->name }}</p>
                                <p class="max-w-[14rem] truncate text-xs text-slate-500">{{ $user->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                                >
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                <main id="main-content" class="min-w-0 flex-1">
                    <div class="mx-auto w-full min-w-0 max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
