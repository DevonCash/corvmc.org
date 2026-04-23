<div class="flex gap-3" x-data>
    <x-filament::button
        type="submit"
        icon="tabler-credit-card"
        color="success"
        x-on:click="$el.closest('form').querySelector('[name=\'data.payment_method\']').value = 'stripe'"
    >
        Pay Online
    </x-filament::button>

    <x-filament::button
        type="submit"
        icon="tabler-cash"
        color="warning"
        x-on:click="$el.closest('form').querySelector('[name=\'data.payment_method\']').value = 'cash'"
    >
        Pay with Cash
    </x-filament::button>
</div>
