<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">Reset your password</h1>
        <p class="mt-1 text-sm text-slate-500">Enter your email and we will send a reset link if an account exists.</p>
    </div>

    @if ($status)
        <div class="mb-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-800" role="status">
            {{ $status }}
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
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

        <x-primary-button wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="sendResetLink">Email reset link</span>
            <span wire:loading wire:target="sendResetLink">Sending…</span>
        </x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:text-brand-800" wire:navigate>Back to sign in</a>
    </p>
</div>
