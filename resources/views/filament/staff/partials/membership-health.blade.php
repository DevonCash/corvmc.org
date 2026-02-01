<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="p-6">
        {{-- Header --}}
        <div class="flex items-center gap-3 mb-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-50 dark:bg-cyan-900/10">
                <x-tabler-heart-handshake class="h-5 w-5 text-cyan-600 dark:text-cyan-400" />
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Membership Health
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Sustaining member metrics
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Sustaining Members --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-user-heart class="h-5 w-5 text-green-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Sustaining Members</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($data['sustaining_members']) }}
                    </span>
                    @if($data['subscription_net_change'] != 0)
                        <span class="flex items-center gap-0.5 text-sm font-medium {{ $data['subscription_net_change'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            @if($data['subscription_net_change'] > 0)
                                <x-tabler-trending-up class="h-4 w-4" />
                                +{{ $data['subscription_net_change'] }}
                            @else
                                <x-tabler-trending-down class="h-4 w-4" />
                                {{ $data['subscription_net_change'] }}
                            @endif
                        </span>
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $data['active_subscriptions'] }} active subscriptions
                </div>
            </div>

            {{-- MRR --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-currency-dollar class="h-5 w-5 text-cyan-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Monthly Revenue</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['mrr_total'] }}
                    </span>
                    <span class="ml-1 text-sm text-red-500 dark:text-red-400">
                        (-{{ $data['fee_cost'] }} fees)
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Net: {{ $data['mrr_base'] }}
                </div>
            </div>

            {{-- Average & Median --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-chart-bar class="h-5 w-5 text-purple-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Contribution</span>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ $data['average_mrr'] }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Average</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ $data['median_contribution'] }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Median</div>
                    </div>
                </div>
            </div>

            {{-- New Members --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-tabler-user-plus class="h-5 w-5 text-amber-500" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">New This Month</span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($data['new_members_this_month']) }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Members joined in {{ now()->format('F') }}
                </div>
            </div>
        </div>
    </div>
</div>
