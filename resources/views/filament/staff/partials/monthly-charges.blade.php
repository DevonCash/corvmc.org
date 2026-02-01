<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="p-6">
        {{-- Header --}}
        <div class="flex items-center gap-3 mb-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-900/10">
                <x-tabler-receipt class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ now()->format('F') }} Charges
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Practice space & other charges
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Collected --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-circle-check class="h-5 w-5 text-green-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Collected</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['total_paid'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $data['paid_count'] }} paid charges
                </div>
            </div>

            {{-- Outstanding --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-clock-dollar class="h-5 w-5 text-amber-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Outstanding</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['total_pending'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $data['pending_count'] }} pending charges
                </div>
            </div>

            {{-- Credits Applied --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-gift class="h-5 w-5 text-purple-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Credits Used</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['total_credits_applied'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Free hours applied
                </div>
            </div>

            {{-- Payment Methods --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-credit-card class="h-5 w-5 text-blue-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">By Method</span>
                </div>
                <div class="mt-2 space-y-1">
                    @forelse($data['by_payment_method'] as $method)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400 capitalize">
                                {{ $method['method'] === 'credits' ? 'Free Hours' : $method['method'] }}
                            </span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ \Brick\Money\Money::ofMinor($method['total'], 'USD')->formatTo('en_US') }}
                            </span>
                        </div>
                    @empty
                        <span class="text-sm text-gray-500 dark:text-gray-400">No payments yet</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Summary footer --}}
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">
                    Total gross (before credits)
                </span>
                <span class="font-medium text-gray-900 dark:text-white">
                    {{ $data['total_gross'] }}
                </span>
            </div>
            @if($data['comped_count'] > 0)
                <div class="flex items-center justify-between text-sm mt-1">
                    <span class="text-gray-600 dark:text-gray-400">
                        Comped
                    </span>
                    <span class="font-medium text-gray-500 dark:text-gray-400">
                        {{ $data['comped_count'] }} charges
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
