<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to continue to your inventory workspace.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-800" role="status">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <x-input-label for="email" value="Email address" />
            <x-text-input
                wire:model="email"
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="username"
                placeholder="you@company.com"
            />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <x-input-label for="password" value="Password" class="mb-0" />
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800" wire:navigate>
                    Forgot password?
                </a>
            </div>
            <x-text-input
                wire:model="password"
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Enter your password"
            />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex items-center gap-2">
            <input
                wire:model="remember"
                id="remember"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
            >
            <label for="remember" class="text-sm text-slate-600">Remember me</label>
        </div>

        <x-primary-button wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        New to StockPilot?
        <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:text-brand-800" wire:navigate>Create an account</a>
    </p>
</div>
