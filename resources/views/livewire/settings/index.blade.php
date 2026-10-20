<div class="space-y-6">
    <x-page-header
        title="Settings"
        subtitle="Configure business profile and display preferences. Changing the currency symbol or date format only affects how values are shown. Inventory quantities, stock history, and purchase-order costs are never rewritten."
    />

    @if ($statusMessage)
        <x-notice type="status">{{ $statusMessage }}</x-notice>
    @endif

    <form wire:submit="save" class="space-y-6">
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                Please correct the highlighted fields below.
            </div>
        @endif

        <x-form-panel
            title="Business profile"
            subtitle="Shown in the application chrome and useful for local workspace identity."
            title-id="settings-business-title"
            class="shadow-sm"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="business_name" value="Business name" :required="true" />
                    <x-text-input wire:model="business_name" id="business_name" type="text" required aria-required="true" autocomplete="organization" />
                    <x-input-error :messages="$errors->get('business_name')" />
                </div>
                <div>
                    <x-input-label for="business_email" value="Business email" :optional="true" />
                    <x-text-input wire:model="business_email" id="business_email" type="email" autocomplete="email" />
                    <x-input-error :messages="$errors->get('business_email')" />
                </div>
                <div>
                    <x-input-label for="business_phone" value="Business phone" :optional="true" />
                    <x-text-input wire:model="business_phone" id="business_phone" type="text" autocomplete="tel" />
                    <x-input-error :messages="$errors->get('business_phone')" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="business_address" value="Business address" :optional="true" />
                    <x-textarea-input wire:model="business_address" id="business_address" rows="3" />
                    <x-input-error :messages="$errors->get('business_address')" />
                </div>
            </div>
        </x-form-panel>

        <x-form-panel
            title="Inventory preferences"
            subtitle="Defaults apply to new products and list pages. Existing products and historical records stay unchanged."
            title-id="settings-inventory-title"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="default_reorder_level" value="Default low-stock threshold" :required="true" />
                    <x-text-input wire:model="default_reorder_level" id="default_reorder_level" type="number" min="0" step="1" required aria-required="true" />
                    <x-form-help>Used as the reorder level when creating a new product.</x-form-help>
                    <x-input-error :messages="$errors->get('default_reorder_level')" />
                </div>
                <div>
                    <x-input-label for="default_per_page" value="Default items per page" :required="true" />
                    <x-select-input wire:model="default_per_page" id="default_per_page" required aria-required="true">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </x-select-input>
                    <x-form-help>Applies to catalog and transaction list pages.</x-form-help>
                    <x-input-error :messages="$errors->get('default_per_page')" />
                </div>
            </div>
        </x-form-panel>

        <x-form-panel
            title="Display preferences"
            subtitle="Presentation only. No currency conversion is performed, and stored timestamps are not altered."
            title-id="settings-display-title"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="date_format" value="Date display format" :required="true" />
                    <x-select-input wire:model="date_format" id="date_format" required aria-required="true">
                        @foreach ($dateFormats as $value => $label)
                            <option value="{{ $value }}">{{ $label }} ({{ now()->format($value) }})</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('date_format')" />
                </div>
                <div>
                    <x-input-label for="currency_symbol" value="Currency display symbol" :required="true" />
                    <x-text-input wire:model.live="currency_symbol" id="currency_symbol" type="text" maxlength="8" required aria-required="true" />
                    <x-form-help>
                        Example preview: {{ $currency_symbol !== '' ? $currency_symbol : '$' }}12.50
                    </x-form-help>
                    <x-input-error :messages="$errors->get('currency_symbol')" />
                </div>
            </div>
        </x-form-panel>

        <x-form-actions class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:px-6">
            <x-primary-button type="submit" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save settings</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-primary-button>
            <p class="text-xs text-slate-500 sm:ml-auto">Meaningful settings changes are recorded in Audit Logs.</p>
        </x-form-actions>
    </form>
</div>
