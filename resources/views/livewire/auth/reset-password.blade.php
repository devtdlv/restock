<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">Choose a new password</h1>
        <p class="mt-1 text-sm text-slate-500">Enter your email and a new password to finish resetting access.</p>
    </div>

    <form wire:submit="resetPassword" class="space-y-4">
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
            />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="New password" />
            <x-text-input
                wire:model="password"
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Create a strong password"
            />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Repeat your password"
            />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-primary-button wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="resetPassword">Reset password</span>
            <span wire:loading wire:target="resetPassword">Updating…</span>
        </x-primary-button>
    </form>
</div>
