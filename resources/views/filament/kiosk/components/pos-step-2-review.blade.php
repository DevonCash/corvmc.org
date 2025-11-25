<div class="max-w-3xl mx-auto">
    <h2 class="text-2xl font-bold mb-6">Review Your Order</h2>

    <div class="p-8 bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700">
        <div class="space-y-4 mb-6">
            @foreach($this->getCartItems() as $item)
                <div class="flex justify-between items-center py-3 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex-1">
                        <div class="text-lg font-semibold">{{ $item['product']->name }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $item['product']->price->formatTo('en_US') }} × {{ $item['quantity'] }}
                        </div>
                    </div>
                    <div class="text-lg font-bold">
                        {{ $item['subtotal']->formatTo('en_US') }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-t-2 border-gray-300 dark:border-gray-600 pt-4 mb-6">
            <div class="flex justify-between items-center">
                <span class="text-2xl font-bold">Total:</span>
                <span class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                    ${{ number_format($this->getCartTotal() / 100, 2) }}
                </span>
            </div>
        </div>

        <div class="mb-6">
            {{ $this->form }}
        </div>
    </div>
</div>
