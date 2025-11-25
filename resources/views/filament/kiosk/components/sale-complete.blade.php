@if($sale)
    <div class="space-y-6">
        <div class="text-center">
            <x-filament::icon
                icon="heroicon-o-check-circle"
                class="w-20 h-20 mx-auto text-success-600 mb-4"
            />
            <div class="text-3xl font-bold text-success-600">Payment Successful!</div>
        </div>

        <div class="border-t border-b border-gray-300 dark:border-gray-600 py-4 space-y-3">
            @foreach($sale->items as $item)
                <div class="flex justify-between text-lg">
                    <span>{{ $item->description }} × {{ $item->quantity }}</span>
                    <span class="font-semibold">{{ $item->subtotal->formatTo('en_US') }}</span>
                </div>
            @endforeach
        </div>

        <div class="space-y-3">
            <div class="flex justify-between items-center text-xl font-bold">
                <span>Total:</span>
                <span class="text-primary-600">{{ $sale->total->formatTo('en_US') }}</span>
            </div>
            <div class="flex justify-between items-center text-lg">
                <span>Tendered:</span>
                <span>{{ $sale->tendered_amount->formatTo('en_US') }}</span>
            </div>
            <div class="flex justify-between items-center text-2xl font-bold text-success-600">
                <span>Change:</span>
                <span>${{ number_format($changeDue / 100, 2) }}</span>
            </div>
        </div>

        @if($sale->user)
            <div class="text-center text-gray-600 dark:text-gray-400">
                Customer: <span class="font-semibold">{{ $sale->user->name }}</span>
            </div>
        @endif

        <div class="flex gap-4 mt-6">
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
        </div>
    </div>
@else
    <div class="text-center text-gray-500 py-8">
        Processing sale...
    </div>
@endif
