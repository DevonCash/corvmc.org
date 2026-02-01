<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="p-6">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-50 dark:bg-cyan-900/10">
                    <x-tabler-calendar-event class="h-5 w-5 text-cyan-600 dark:text-cyan-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        Today's Operations
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ now()->format('l, F j, Y') }}
                    </p>
                </div>
            </div>

            {{-- Space Status Badge --}}
            @if($data['space_status']['is_open'])
                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-green-600 dark:bg-green-400"></span>
                    Space Open
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/20" title="{{ $data['space_status']['reason'] }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
                    Closed: {{ $data['space_status']['reason'] }}
                </span>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Today's Reservations --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Reservations</h4>
                    <a href="{{ route('filament.staff.resources.space-management.index') }}" class="text-xs text-cyan-600 hover:text-cyan-500 dark:text-cyan-400">
                        View all
                    </a>
                </div>

                @if($data['reservations']->isEmpty())
                    <div class="flex flex-col items-center justify-center py-8 text-center rounded-lg bg-gray-50 dark:bg-gray-800/50">
                        <x-tabler-calendar-off class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No reservations today</p>
                    </div>
                @else
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($data['reservations'] as $reservation)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex flex-col items-center justify-center px-2 py-1 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 min-w-[52px]">
                                        <div class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                            {{ $reservation['start_time'] }}
                                        </div>
                                        <div class="text-[10px] text-gray-400 dark:text-gray-500">
                                            {{ $reservation['duration'] }}h
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ $reservation['title'] }}
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium
                                                {{ $reservation['status']->getColor() === 'success' ? 'bg-green-50 text-green-700 dark:bg-green-400/10 dark:text-green-400' : '' }}
                                                {{ $reservation['status']->getColor() === 'warning' ? 'bg-yellow-50 text-yellow-700 dark:bg-yellow-400/10 dark:text-yellow-400' : '' }}
                                                {{ $reservation['status']->getColor() === 'danger' ? 'bg-red-50 text-red-700 dark:bg-red-400/10 dark:text-red-400' : '' }}
                                                {{ $reservation['status']->getColor() === 'gray' ? 'bg-gray-50 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400' : '' }}
                                                {{ $reservation['status']->getColor() === 'info' ? 'bg-blue-50 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400' : '' }}
                                            ">
                                                {{ $reservation['status']->getLabel() }}
                                            </span>
                                            @if($reservation['amount'] && !$reservation['is_paid'])
                                                <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400">
                                                    <x-tabler-currency-dollar class="h-3 w-3" />
                                                    {{ $reservation['amount'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Stats Row --}}
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-4">
                            <div class="text-center">
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $data['stats']['total_hours'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Hours</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $data['stats']['total_revenue'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Revenue</div>
                            </div>
                            @if($data['stats']['unpaid_count'] > 0)
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-amber-600 dark:text-amber-400">{{ $data['stats']['unpaid_count'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Unpaid</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right Column: Tonight's Event & Equipment --}}
            <div class="space-y-6">
                {{-- Tonight's Event --}}
                <div>
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Tonight's Event</h4>
                    @if($data['tonights_event'])
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex-shrink-0">
                                    <x-tabler-music class="h-5 w-5 text-cyan-600 dark:text-cyan-400" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $data['tonights_event']->title }}
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <x-tabler-clock class="h-3 w-3" />
                                        <span>{{ $data['tonights_event']->start_datetime->format('g:i A') }}</span>
                                        @if($data['tonights_event']->venue)
                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                            <x-tabler-map-pin class="h-3 w-3" />
                                            <span>{{ $data['tonights_event']->venue->name }}</span>
                                        @endif
                                    </div>
                                    @if($data['tonights_event']->organizer)
                                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            <x-tabler-user class="h-3 w-3" />
                                            <span>{{ $data['tonights_event']->organizer->name }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-6 text-center rounded-lg bg-gray-50 dark:bg-gray-800/50">
                            <x-tabler-calendar-x class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No events tonight</p>
                        </div>
                    @endif
                </div>

                {{-- Equipment Checked Out --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Equipment Out</h4>
                        @if($data['checked_out_equipment']->isNotEmpty())
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $data['checked_out_equipment']->count() }} items</span>
                        @endif
                    </div>
                    @if($data['checked_out_equipment']->isEmpty())
                        <div class="flex flex-col items-center justify-center py-6 text-center rounded-lg bg-gray-50 dark:bg-gray-800/50">
                            <x-tabler-package class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No equipment checked out</p>
                        </div>
                    @else
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            @foreach($data['checked_out_equipment'] as $loan)
                                <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-tabler-tool class="h-4 w-4 text-gray-400 flex-shrink-0" />
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                {{ $loan['equipment_name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                {{ $loan['borrower_name'] }}
                                            </div>
                                        </div>
                                    </div>
                                    @if($loan['is_overdue'])
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/20">
                                            <x-tabler-alert-triangle class="h-3 w-3" />
                                            {{ $loan['days_overdue'] }}d overdue
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            Due {{ $loan['due_at']->format('M j') }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
