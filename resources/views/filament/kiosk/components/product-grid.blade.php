<div class="grid grid-cols-3 gap-6">
    {{-- Left: Product Grid --}}
    <div class="col-span-2 space-y-4">
        {{-- Category Tabs --}}
        <div class="flex gap-2">
            @foreach($categories as $value => $label)
                <x-filament::button
                    wire:click="setCategory('{{ $value }}')"
                    :color="$activeCategory === $value ? 'primary' : 'gray'"
                    size="lg"
                >
                    {{ $label }}
                </x-filament::button>
            @endforeach
        </div>

        {{-- Product Grid --}}
        <div class="grid grid-cols-3 gap-4">
            @forelse($products as $product)
                <button
                    type="button"
                    wire:click="addToCart({{ $product->id }})"
                    class="p-6 bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 transition-colors text-center"
                >
                    <div class="text-lg font-semibold mb-2">{{ $product->name }}</div>
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $product->price->formatTo('en_US') }}
                    </div>
                    @if($product->description)
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            {{ $product->description }}
                        </div>
                    @endif
                </button>
            @empty
                <div class="col-span-3 p-8 text-center text-gray-500">
                    No products available
                </div>
            @endforelse
        </div>
    </div>

    {{-- Right: Cart Sidebar --}}
    <div class="space-y-4">
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 sticky top-4">
            <h3 class="text-xl font-bold mb-4">Cart</h3>

            @if($cartItems->isEmpty())
                <div class="text-center text-gray-500 py-8">
                    Cart is empty
                </div>
            @else
                <div class="space-y-3 mb-4 max-h-96 overflow-y-auto">
                    @foreach($cartItems as $item)
                        <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex-1">
                                <div class="font-semibold">{{ $item['product']->name }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $item['product']->price->formatTo('en_US') }} × {{ $item['quantity'] }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::button
                                    type="button"
                                    wire:click="removeFromCart({{ $item['product']->id }})"
                                    color="danger"
                                    size="xs"
                                    icon="heroicon-m-minus"
                                />
                                <span class="font-bold min-w-[3rem] text-center">
                                    {{ $item['subtotal']->formatTo('en_US') }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t-2 border-gray-300 dark:border-gray-600 pt-4 mb-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xl font-bold">Total:</span>
                        <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                            ${{ number_format($cartTotal / 100, 2) }}
                        </span>
                    </div>
                </div>

                <x-filament::button
                    type="button"
                    wire:click="clearCart"
                    size="lg"
                    color="gray"
                    class="w-full"
                >
                    Clear Cart
                </x-filament::button>
            @endif
        </div>
    </div>
</div>
