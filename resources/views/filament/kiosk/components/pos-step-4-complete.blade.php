<div class="max-w-2xl mx-auto">
    @if($completedSale)
        <div class="p-8 bg-white dark:bg-gray-800 rounded-xl border-2 border-success-600">
            <div class="text-center mb-8">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="w-24 h-24 mx-auto text-success-600 mb-4"
                />
                <h2 class="text-4xl font-bold text-success-600 mb-2">Payment Successful!</h2>
                <p class="text-gray-600 dark:text-gray-400">Transaction completed</p>
            </div>

            <div class="border-t border-b border-gray-300 dark:border-gray-600 py-6 space-y-3 mb-6">
                @foreach($completedSale->items as $item)
                    <div class="flex justify-between text-lg">
                        <span>{{ $item->description }} × {{ $item->quantity }}</span>
                        <span class="font-semibold">{{ $item->subtotal->formatTo('en_US') }}</span>
                    </div>
                @endforeach
            </div>

            <div class="space-y-4 mb-6">
                <div class="flex justify-between items-center text-2xl font-bold">
                    <span>Total:</span>
                    <span class="text-primary-600">{{ $completedSale->total->formatTo('en_US') }}</span>
                </div>
                <div class="flex justify-between items-center text-xl">
                    <span>Tendered:</span>
                    <span>{{ $completedSale->tendered_amount->formatTo('en_US') }}</span>
                </div>
                <div class="flex justify-between items-center text-3xl font-bold text-success-600">
                    <span>Change:</span>
                    <span>{{ $completedSale->change_amount->formatTo('en_US') }}</span>
                </div>
            </div>

            @if($completedSale->user)
                <div class="text-center pt-6 border-t border-gray-300 dark:border-gray-600">
                    <div class="text-gray-600 dark:text-gray-400">
                        Customer: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $completedSale->user->name }}</span>
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="text-center text-gray-500 py-8">
            Processing sale...
        </div>
    @endif
</div>
