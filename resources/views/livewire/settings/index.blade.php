<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Settings</h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">
            Configure business profile and display preferences for StockPilot.
            Changing the currency symbol or date format only affects how values are shown.
            Inventory quantities, stock history, and purchase-order costs are never rewritten.
        </p>
    </div>

    @if ($statusMessage)
        <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">Business profile</h2>
            <p class="mt-1 text-sm text-slate-500">Shown in the application chrome and useful for local workspace identity.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="business_name" value="Business name" />
                    <x-text-input wire:model="business_name" id="business_name" type="text" required autocomplete="organization" />
                    <x-input-error :messages="$errors->get('business_name')" />
                </div>
                <div>
                    <x-input-label for="business_email" value="Business email (optional)" />
                    <x-text-input wire:model="business_email" id="business_email" type="email" autocomplete="email" />
                    <x-input-error :messages="$errors->get('business_email')" />
                </div>
                <div>
                    <x-input-label for="business_phone" value="Business phone (optional)" />
                    <x-text-input wire:model="business_phone" id="business_phone" type="text" autocomplete="tel" />
                    <x-input-error :messages="$errors->get('business_phone')" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="business_address" value="Business address (optional)" />
                    <x-textarea-input wire:model="business_address" id="business_address" rows="3" />
                    <x-input-error :messages="$errors->get('business_address')" />
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">Inventory preferences</h2>
            <p class="mt-1 text-sm text-slate-500">
                Defaults apply to new products and list pages. Existing products and historical records stay unchanged.
            </p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="default_reorder_level" value="Default low-stock threshold" />
                    <x-text-input wire:model="default_reorder_level" id="default_reorder_level" type="number" min="0" step="1" />
                    <p class="mt-1 text-xs text-slate-500">Used as the reorder level when creating a new product.</p>
                    <x-input-error :messages="$errors->get('default_reorder_level')" />
                </div>
                <div>
                    <x-input-label for="default_per_page" value="Default items per page" />
                    <select
                        wire:model="default_per_page"
                        id="default_per_page"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    >
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('default_per_page')" />
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">Display preferences</h2>
            <p class="mt-1 text-sm text-slate-500">
                Presentation only. No currency conversion is performed, and stored timestamps are not altered.
            </p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="date_format" value="Date display format" />
                    <select
                        wire:model="date_format"
                        id="date_format"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    >
                        @foreach ($dateFormats as $value => $label)
                            <option value="{{ $value }}">{{ $label }} ({{ now()->format($value) }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('date_format')" />
                </div>
                <div>
                    <x-input-label for="currency_symbol" value="Currency display symbol" />
                    <x-text-input wire:model="currency_symbol" id="currency_symbol" type="text" maxlength="8" />
                    <p class="mt-1 text-xs text-slate-500">Example preview: {{ $currency_symbol !== '' ? $currency_symbol : '$' }}12.50</p>
                    <x-input-error :messages="$errors->get('currency_symbol')" />
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <x-primary-button type="submit" class="w-auto" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Save settings</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-primary-button>
            <p class="text-xs text-slate-500">Meaningful settings changes are recorded in Audit Logs.</p>
        </div>
    </form>
</div>
