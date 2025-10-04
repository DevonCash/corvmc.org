<x-filament-widgets::widget>
    @php
        $data = $this->getViewData();
        $next = $data['nextItem'];
        $nextType = $data['nextType'];
        $today = $data['todaysUsage'];
    @endphp

    <x-filament::section>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Next Space Usage Card --}}
            <div>
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <x-filament::icon icon="tabler-clock" class="w-5 h-5" />
                    Next Space Usage
                </h3>

                @if($next)
                    @php
                        $isRehearsalReservation = $next instanceof \App\Models\RehearsalReservation;
                        $typeColor = $isRehearsalReservation ? 'primary' : 'warning';
                    @endphp

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <x-filament::badge :color="$typeColor">
                                        {{ $nextType }}
                                    </x-filament::badge>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                                    {{ $next->reserved_at->format('l, F j') }}
                                </div>
                                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                    {{ $next->reserved_at->format('g:i A') }} - {{ $next->reserved_until->format('g:i A') }}
                                </div>
                            </div>
                            <x-filament::badge :color="match($next->status ?? 'confirmed') {
                                'confirmed' => 'success',
                                'pending' => 'warning',
                                default => 'gray'
                            }">
                                {{ ucfirst($next->status ?? 'confirmed') }}
                            </x-filament::badge>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-sm">
                                <x-filament::icon
                                    icon="{{ $next->getReservationIcon() }}"
                                    class="w-4 h-4 text-gray-400"
                                />
                                <span class="font-medium">{{ $next->getDisplayTitle() }}</span>
                            </div>

                            <div class="flex items-center gap-2 text-sm">
                                <x-filament::icon icon="tabler-clock" class="w-4 h-4 text-gray-400" />
                                <span>{{ number_format($next->duration, 1) }} hours</span>
                            </div>

                            @if($isRehearsalReservation && $next->cost->isPositive())
                                <div class="flex items-center gap-2 text-sm">
                                    <x-filament::icon icon="tabler-currency-dollar" class="w-4 h-4 text-gray-400" />
                                    <span>{{ $next->cost_display }}</span>
                                    @if($next->free_hours_used > 0)
                                        <span class="text-success-600 dark:text-success-400">
                                            ({{ number_format($next->free_hours_used, 1) }}h free)
                                        </span>
                                    @endif
                                </div>
                            @endif

                            @if($isRehearsalReservation && $next->payment_status === 'unpaid')
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <x-filament::badge color="danger">
                                        Payment Required
                                    </x-filament::badge>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Starting {{ $next->reserved_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center">
                        <x-filament::icon icon="tabler-calendar-off" class="w-12 h-12 mx-auto text-gray-400 mb-2" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming space usage</p>
                    </div>
                @endif
            </div>

            {{-- Today's Schedule --}}
            <div>
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <x-filament::icon icon="tabler-calendar-today" class="w-5 h-5" />
                    Today's Schedule
                    <x-filament::badge>{{ $data['todaysCount'] }}</x-filament::badge>
                </h3>

                @if($today->isNotEmpty())
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach($today as $item)
                            @php
                                $isRehearsalRes = $item['type_class'] === \App\Models\RehearsalReservation::class;
                            @endphp
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 bg-white dark:bg-gray-800 hover:border-primary-500 transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <x-filament::badge size="xs" :color="$isRehearsalRes ? 'primary' : 'warning'">
                                                {{ $isRehearsalRes ? 'R' : 'P' }}
                                            </x-filament::badge>
                                            <span class="font-medium text-sm">{{ $item['start']->format('g:i A') }}</span>
                                            <span class="text-gray-400">→</span>
                                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $item['end']->format('g:i A') }}</span>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                            {{ $item['title'] }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($item['payment_status'] === 'unpaid')
                                            <x-filament::icon
                                                icon="tabler-credit-card-off"
                                                class="w-4 h-4 text-danger-500"
                                                x-tooltip="'Unpaid'"
                                            />
                                        @endif
                                        <x-filament::badge size="sm" :color="match($item['status']) {
                                            'confirmed' => 'success',
                                            'pending' => 'warning',
                                            default => 'gray'
                                        }">
                                            {{ substr(ucfirst($item['status']), 0, 1) }}
                                        </x-filament::badge>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ number_format($data['hoursToday'], 1) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Total Hours</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                                ${{ number_format($data['revenueToday'], 2) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Revenue</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $data['rehearsalCount'] }} <span class="text-xs text-gray-500">res</span>
                                <span class="text-gray-400 mx-1">•</span>
                                {{ $data['productionCount'] }} <span class="text-xs text-gray-500">prod</span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Bookings</div>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center">
                        <x-filament::icon icon="tabler-calendar-off" class="w-12 h-12 mx-auto text-gray-400 mb-2" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">No space usage today</p>
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
