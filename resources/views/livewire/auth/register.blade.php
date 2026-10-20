<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">Create your account</h1>
        <p class="mt-1 text-sm text-slate-500">Set up {{ app(\App\Support\AppDisplay::class)->businessName() }} for your local inventory operations.</p>
    </div>

    <form wire:submit="register" class="space-y-5">
        <div>
            <x-input-label for="name" value="Full name" :required="true" />
            <x-text-input
                wire:model="name"
                id="name"
                type="text"
                name="name"
                required
                aria-required="true"
                autofocus
                autocomplete="name"
                placeholder="Alex Rivera"
            />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Email address" :required="true" />
            <x-text-input
                wire:model="email"
                id="email"
                type="email"
                name="email"
                required
                aria-required="true"
                autocomplete="username"
                placeholder="you@company.com"
            />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="Password" :required="true" />
            <x-text-input
                wire:model="password"
                id="password"
                type="password"
                name="password"
                required
                aria-required="true"
                autocomplete="new-password"
                placeholder="Create a strong password"
            />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm password" :required="true" />
            <x-text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                aria-required="true"
                autocomplete="new-password"
                placeholder="Repeat your password"
            />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-primary-button wire:loading.attr="disabled" wire:target="register">
            <span wire:loading.remove wire:target="register">Create account</span>
            <span wire:loading wire:target="register">Creating account…</span>
        </x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:text-brand-800" wire:navigate>Sign in</a>
    </p>
</div>
