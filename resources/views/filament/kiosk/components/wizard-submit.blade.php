@php
    $currentStep = request()->query('step', 1);
@endphp

<div class="flex gap-4 mt-6">
    <x-filament::button
        type="button"
        wire:click="cancelSale"
        color="gray"
        size="xl"
    >
        <x-filament::icon icon="heroicon-o-arrow-left" class="w-6 h-6 mr-2" />
        Cancel & Return to Dashboard
    </x-filament::button>

    @if($currentStep < 3)
        <x-filament::button
            type="submit"
            color="primary"
            size="xl"
            class="flex-1"
        >
            @if($currentStep == 1)
                <x-filament::icon icon="heroicon-o-arrow-right" class="w-6 h-6 mr-2" />
                Continue to Review
            @elseif($currentStep == 2)
                <x-filament::icon icon="heroicon-o-arrow-right" class="w-6 h-6 mr-2" />
                Continue to Payment
            @endif
        </x-filament::button>
    @elseif($currentStep == 3)
        <x-filament::button
            type="submit"
            color="success"
            size="xl"
            class="flex-1"
        >
            <x-filament::icon icon="heroicon-o-credit-card" class="w-6 h-6 mr-2" />
            Process Payment
        </x-filament::button>
    @else
        <x-filament::button
            type="button"
            wire:click="finishSale"
            color="success"
            size="xl"
            class="flex-1"
        >
            <x-filament::icon icon="heroicon-o-home" class="w-6 h-6 mr-2" />
            New Sale
        </x-filament::button>
    @endif
</div>
