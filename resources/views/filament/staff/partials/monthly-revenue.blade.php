<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="p-6">
        {{-- Header --}}
        <div class="flex items-center gap-3 mb-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-900/10">
                <x-tabler-report-money class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ now()->format('F') }} Revenue
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Subscriptions + practice space charges
                </p>
            </div>
        </div>

        {{-- Main stats --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            {{-- Total Revenue --}}
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20">
                <div class="flex items-center gap-2">
                    <x-tabler-cash class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Total Revenue</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">
                        {{ $data['total_gross'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                    Net after fees: {{ $data['total_net'] }}
                </div>
            </div>

            {{-- Subscriptions --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-heart-handshake class="h-5 w-5 text-cyan-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Subscriptions</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['subscriptions_total'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $data['sustaining_members'] }} sustaining members
                </div>
            </div>

            {{-- Practice Space --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-building class="h-5 w-5 text-purple-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Practice Space</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['charges_collected'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if($data['charges_pending_count'] > 0)
                        <span class="text-amber-600 dark:text-amber-400">{{ $data['charges_pending'] }} pending</span>
                    @else
                        All collected
                    @endif
                </div>
            </div>

            {{-- Cash --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-cash-banknote class="h-5 w-5 text-green-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Cash Collected</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['cash_collected'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    No processing fees
                </div>
            </div>
        </div>

        {{-- Secondary stats --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Sustaining Members --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-user-heart class="h-5 w-5 text-pink-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Members</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ $data['sustaining_members'] }}
                    </span>
                    @if($data['subscription_net_change'] != 0)
                        <span class="text-sm font-medium {{ $data['subscription_net_change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $data['subscription_net_change'] > 0 ? '+' : '' }}{{ $data['subscription_net_change'] }}
                        </span>
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $data['new_members_this_month'] }} new this month
                </div>
            </div>

            {{-- Average Contribution --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-chart-bar class="h-5 w-5 text-blue-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Avg / Median</span>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div>
                        <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $data['average_contribution'] }}</div>
                        <div class="text-xs text-gray-500">avg</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $data['median_contribution'] }}</div>
                        <div class="text-xs text-gray-500">median</div>
                    </div>
                </div>
            </div>

            {{-- Credits Applied --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-gift class="h-5 w-5 text-violet-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Free Hours Used</span>
                </div>
                <div class="mt-2">
                    <span class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ $data['credits_applied'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Member benefit value
                </div>
            </div>

            {{-- Processing Fees --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-receipt-tax class="h-5 w-5 text-red-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Stripe Fees</span>
                </div>
                <div class="mt-2">
                    <span class="text-xl font-bold text-gray-900 dark:text-white">
                        -{{ $data['total_fees'] }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    2.9% + $0.30/txn
                </div>
            </div>
        </div>
    </div>
</div>
