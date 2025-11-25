<x-filament-panels::page>
    <div class="mb-6">
        <div class="p-6 bg-success-50 dark:bg-success-950 rounded-xl border-2 border-success-600">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-lg font-semibold text-success-900 dark:text-success-100">
                        Today's Sales
                    </div>
                    <div class="text-3xl font-bold text-success-700 dark:text-success-300">
                        {{ $this->getTodayTotal() }}
                    </div>
                </div>
                <x-filament::icon
                    icon="heroicon-o-currency-dollar"
                    class="w-12 h-12 text-success-600"
                />
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
