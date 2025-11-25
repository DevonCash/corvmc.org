<x-filament-panels::page>
    <div class="fixed inset-0 flex flex-col bg-gray-50 dark:bg-gray-950">
        {{-- Main Content Area (Scrollable) --}}
        <div class="flex-1 overflow-y-auto pb-24 pt-16">
            <div class="max-w-7xl mx-auto p-6">
                @if($currentStep === 1)
                    @include('filament.kiosk.components.pos-step-1-cart')
                @elseif($currentStep === 2)
                    @include('filament.kiosk.components.pos-step-2-review')
                @elseif($currentStep === 3)
                    @include('filament.kiosk.components.pos-step-3-payment')
                @elseif($currentStep === 4)
                    @include('filament.kiosk.components.pos-step-4-complete')
                @endif
            </div>
        </div>

        {{-- Fixed Bottom Navigation --}}
        <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700 p-4 shadow-lg">
            <div class="max-w-7xl mx-auto">
                @if($currentStep === 1)
                    <div class="flex gap-4 justify-between w-full">
                        <x-filament::button
                            wire:click="cancelSale"
                            color="gray"
                            size="xl"
                            icon="heroicon-o-arrow-left"
                        >
                            Cancel
                        </x-filament::button>

                        <x-filament::button
                            wire:click="continueToReview"
                            color="primary"
                            size="xl"
                        >
                            Review Cart
                            <x-filament::icon icon="heroicon-o-arrow-right" class="w-6 h-6 ml-2" />
                        </x-filament::button>
                    </div>
                @elseif($currentStep === 2)
                    <div class="flex gap-4">
                        <x-filament::button
                            wire:click="backToStep(1)"
                            color="gray"
                            size="xl"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-left" class="w-6 h-6 mr-2" />
                            Back to Cart
                        </x-filament::button>

                        <x-filament::button
                            wire:click="continueToPayment"
                            color="primary"
                            size="xl"
                            class="flex-1"
                        >
                            Continue to Payment
                            <x-filament::icon icon="heroicon-o-arrow-right" class="w-6 h-6 ml-2" />
                        </x-filament::button>
                    </div>
                @elseif($currentStep === 3)
                    <div class="flex gap-4">
                        <x-filament::button
                            wire:click="backToStep(2)"
                            color="gray"
                            size="xl"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-left" class="w-6 h-6 mr-2" />
                            Back
                        </x-filament::button>

                        <x-filament::button
                            wire:click="processPayment"
                            color="success"
                            size="xl"
                            class="flex-1"
                        >
                            <x-filament::icon icon="heroicon-o-credit-card" class="w-6 h-6 mr-2" />
                            Complete Sale
                        </x-filament::button>
                    </div>
                @elseif($currentStep === 4)
                    <x-filament::button
                        wire:click="finishSale"
                        color="success"
                        size="xl"
                        class="w-full"
                    >
                        <x-filament::icon icon="heroicon-o-home" class="w-6 h-6 mr-2" />
                        New Sale
                    </x-filament::button>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
