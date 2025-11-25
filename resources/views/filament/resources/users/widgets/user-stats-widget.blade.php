<x-filament-widgets::widget>
    <div class="grid gap-4 md:grid-cols-3">
        {{-- Total Members Stat --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <x-filament::icon icon="tabler-users" class="size-8 text-primary" />

                <div class="flex-1">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Total Members
                    </div>
                    <div class="text-3xl font-bold text-gray-950 dark:text-white flex items-baseline gap-2">
                        {{ number_format($this->totalMembers) }}
                        <span class="text-sm text-success flex items-baseline gap-[2px]">
                            @if ($this->newMembersThisMonth >= 0)
                                <x-filament::icon icon="tabler-trending-up" class="size-4"
                                    style="top: 3px; position:relative;" />
                            @else
                                <x-filament::icon icon="tabler-trending-down" class="size-4"
                                    style="top: 3px; position:relative;" />
                            @endif
                            {{ number_format($this->newMembersThisMonth) }}
                        </span>
                    </div>

                </div>
            </div>
        </div>

        {{-- Sustaining Members Stat --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <x-filament::icon icon="tabler-user-heart" class="size-8 text-success-500" />
                <div class="flex-1">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Sustaining Members
                    </div>

                    <div class="text-3xl font-bold text-gray-950 dark:text-white flex items-baseline">
                        {{ number_format($this->sustainingMembers) }}
                        <span class="text-sm  flex items-baseline gap-[2px] font-medium">
                            @if ($this->subscriptionNetChangeLastMonth > 0)
                                <x-filament::icon icon="tabler-trending-up" class="size-4 text-success"
                                    style="top: 3px; position:relative;" />
                            @elseif($this->subscriptionNetChangeLastMonth < 0)
                                <x-filament::icon icon="tabler-trending-down" class="size-4 text-error"
                                    style="top: 3px; position:relative;" />
                            @else
                                <x-filament::icon icon="tabler-minus" class="size-4"
                                    style="top: 3px; position:relative;" />
                            @endif
                            {{ number_format($this->subscriptionNetChangeLastMonth) }}
                            this month
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-500">
                        {{ number_format($this->activeSubscriptions) }} active subscriptions
                    </div>
                </div>
            </div>
        </div>

        {{-- MRR Stat --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Member MRR
                    </div>
                    <div class="text-3xl font-bold text-gray-950 dark:text-white">
                        {{ $this->mrrTotal }}
                        <span class="text-sm text-error tracking-tight font-medium">({{ $this->feeCost }} fees)</span>
                    </div>
                    <div class=" grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-500">
                        <div>Avg: <span
                                class="font-medium text-gray-700 dark:text-gray-300">{{ $this->averageMrr }}</span>
                        </div>
                        <div>Median: <span
                                class="font-medium text-gray-700 dark:text-gray-300">{{ $this->medianContribution }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
