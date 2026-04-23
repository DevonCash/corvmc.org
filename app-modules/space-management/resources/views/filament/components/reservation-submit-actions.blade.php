<div class="flex gap-3">
    <x-filament::button
        type="submit"
        icon="tabler-credit-card"
        color="success"
        x-on:click="$wire.set('data.payment_method', 'stripe')"
    >
        Pay Online
    </x-filament::button>

    <x-filament::button
        type="submit"
        icon="tabler-cash"
        color="warning"
        x-on:click="$wire.set('data.payment_method', 'cash')"
    >
        Pay with Cash
    </x-filament::button>
</div>
