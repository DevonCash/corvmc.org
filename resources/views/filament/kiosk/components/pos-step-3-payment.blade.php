<div class="max-w-2xl mx-auto">
    <h2 class="text-2xl font-bold mb-6">Process Payment</h2>

    <div class="p-8 bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 space-y-8">
        <div class="text-center">
            <div class="text-lg text-gray-600 dark:text-gray-400 mb-2">Total Due</div>
            <div class="text-5xl font-bold text-primary-600 dark:text-primary-400">
                ${{ number_format($this->getCartTotal() / 100, 2) }}
            </div>
        </div>

        <div>
            <label class="block text-lg font-semibold mb-2">Amount Tendered</label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-3xl font-bold text-gray-600">$</span>
                <input
                    type="number"
                    step="0.01"
                    wire:model.live.debounce.500ms="tenderedAmount"
                    class="w-full pl-12 pr-4 py-4 text-3xl text-center font-bold border-2 border-gray-300 dark:border-gray-600 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-500 bg-white dark:bg-gray-900"
                    placeholder="0.00"
                    autofocus
                />
            </div>
        </div>

        @if($tenderedAmount)
            <div class="text-center p-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
                <div class="text-lg text-gray-600 dark:text-gray-400 mb-2">Change Due</div>
                <div class="text-5xl font-bold {{ $this->getChangeDue() >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    ${{ number_format($this->getChangeDue() / 100, 2) }}
                </div>
            </div>
        @endif
    </div>
</div>
