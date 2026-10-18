<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' => 'inline-flex w-full items-center justify-center rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60',
    ]) }}
>
    {{ $slot }}
</button>
