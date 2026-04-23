<div class="flex gap-3">
    <x-filament::button
        type="button"
        wire:click="payWithStripe"
        icon="tabler-credit-card"
        color="success"
    >
        Pay Online
    </x-filament::button>

    <x-filament::button
        type="button"
        wire:click="payWithCash"
        icon="tabler-cash"
        color="warning"
    >
        Pay with Cash
    </x-filament::button>
</div>
