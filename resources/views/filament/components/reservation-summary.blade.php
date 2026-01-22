@php
    use App\Models\User;
    use Carbon\Carbon;
    use CorvMC\SpaceManagement\Actions\Reservations\CalculateReservationCost;

    $start = $get('reserved_at');
    $end = $get('reserved_until');
    $userId = $get('user_id');
    $notes = $get('notes');
    $isRecurring = $get('is_recurring');

    if (!$start || !$end || !$userId) {
        $showSummary = false;
    } else {
        $showSummary = true;
        $user = User::find($userId);

        if ($user) {
            $startFormatted = Carbon::parse($start)->format('l, M j, Y');
            $startTime = Carbon::parse($start)->format('g:i A');
            $endTime = Carbon::parse($end)->format('g:i A');
            $duration = Carbon::parse($start)->diffInMinutes(Carbon::parse($end)) / 60;

            $calculation = CalculateReservationCost::run(
                $user,
                Carbon::parse($start),
                Carbon::parse($end)
            );

            $reservationDate = Carbon::parse($start);
            $paidHours = $calculation['total_hours'] - $calculation['free_hours'];
            $isSustainingMember = $user->hasRole('sustaining member');
            $hourlyRate = config('reservation.hourly_rate');
        }
    }
@endphp

@if(!$showSummary)
    <div class="flex items-center gap-2 text-gray-500 text-sm">
        <x-filament::icon
            icon="tabler-arrow-left"
            class="w-5 h-5"
        />
        <span>Complete previous step to see summary</span>
    </div>
@elseif(!$user)
    <div class="flex items-center gap-2 text-danger-600 text-sm">
        <x-filament::icon
            icon="tabler-alert-circle"
            class="w-5 h-5"
        />
        <span>User not found</span>
    </div>
@else
    {{-- Receipt-style summary --}}
    <div class="max-w-md mx-auto">
        <div class="rounded-lg border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            {{-- Header --}}
            <div class="bg-gray-50 dark:bg-gray-800 border-b-2 border-gray-200 dark:border-gray-700 px-6 py-4">
                <div class="flex items-center gap-3 mb-3">
                    <x-filament::icon
                        icon="tabler-receipt"
                        class="w-6 h-6 text-gray-600 dark:text-gray-400"
                    />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Reservation Summary
                    </h3>
                </div>

                <div class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <x-filament::icon
                        icon="tabler-calendar-event"
                        class="w-5 h-5 mt-0.5 flex-shrink-0"
                    />
                    <div>
                        <div class="font-medium">{{ $startFormatted }}</div>
                        <div class="text-gray-600 dark:text-gray-400">{{ $startTime }} - {{ $endTime }}</div>
                    </div>
                </div>
            </div>

            {{-- Line Items --}}
            <div class="px-6 py-4 space-y-3">
                {{-- Total Hours --}}
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                        <x-filament::icon
                            icon="tabler-clock"
                            class="w-5 h-5"
                        />
                        <span class="font-medium">Practice Time</span>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($duration, 1) }} {{ Str::plural('hour', $duration) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @ ${{ number_format($hourlyRate, 2) }}/hr
                        </div>
                    </div>
                </div>

                {{-- Free Hours --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-filament::icon
                            icon="tabler-gift"
                            class="w-5 h-5 {{ $calculation['free_hours'] > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-600' }}"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Free Hours
                            @if(!$isSustainingMember)
                                <a
                                    href="/member/membership"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-primary-600 dark:text-primary-400 hover:underline ml-1"
                                >
                                    (learn more)
                                </a>
                            @endif
                        </span>
                    </div>
                    <div class="font-semibold {{ $calculation['free_hours'] > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-500 dark:text-gray-400' }}">
                        @if($calculation['free_hours'] > 0)
                            -{{ number_format($calculation['free_hours'], 1) }} {{ Str::plural('hour', $calculation['free_hours']) }}
                        @else
                            0.0 hours
                        @endif
                    </div>
                </div>

                {{-- Paid Hours --}}
                @if($paidHours > 0)
                    <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <x-filament::icon
                                icon="tabler-credit-card"
                                class="w-5 h-5"
                            />
                            <span class="text-sm">Paid Hours</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($paidHours, 1) }} {{ Str::plural('hour', $paidHours) }}
                        </span>
                    </div>
                @endif

                {{-- Total --}}
                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center gap-2">
                        <x-filament::icon
                            icon="tabler-receipt-2"
                            class="w-5 h-5 text-gray-900 dark:text-gray-100"
                        />
                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                    </div>
                    <span class="text-2xl font-bold {{ $calculation['cost']->isZero() ? 'text-success-600 dark:text-success-400' : 'text-gray-900 dark:text-gray-100' }}">
                        {{ $calculation['cost']->formatTo('en_US') }}
                    </span>
                </div>

                @if($calculation['cost']->isZero())
                    <div class="flex items-center gap-2 text-sm text-success-700 dark:text-success-300 bg-success-50 dark:bg-success-900/20 rounded-lg px-3 py-2">
                        <x-filament::icon
                            icon="tabler-check"
                            class="w-4 h-4"
                        />
                        <span class="font-medium">Free reservation - no payment required</span>
                    </div>
                @endif
            </div>

            {{-- Notes Section --}}
            @if($notes)
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-start gap-2">
                        <x-filament::icon
                            icon="tabler-note"
                            class="w-5 h-5 text-gray-600 dark:text-gray-400 mt-0.5 flex-shrink-0"
                        />
                        <div class="flex-1">
                            <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">NOTES</div>
                            <div class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $notes }}</div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Footer - Confirmation Info --}}
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                @if($isRecurring)
                    <div class="flex items-start gap-2">
                        <x-filament::icon
                            icon="tabler-repeat"
                            class="w-5 h-5 text-info-600 dark:text-info-400 mt-0.5 flex-shrink-0"
                        />
                        <div class="text-sm">
                            <div class="font-medium text-info-700 dark:text-info-300 mb-1">Recurring Reservation</div>
                            <div class="text-gray-700 dark:text-gray-300">This recurring reservation requires manual approval from staff.</div>
                        </div>
                    </div>
                @elseif($reservationDate->isAfter(Carbon::now()->addWeek()))
                    @php
                        $confirmationDate = $reservationDate->copy()->subDays(3);
                    @endphp
                    <div class="space-y-2">
                        <div class="flex items-start gap-2">
                            <x-filament::icon
                                icon="tabler-mail"
                                class="w-5 h-5 text-primary-600 dark:text-primary-400 mt-0.5 flex-shrink-0"
                            />
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                We'll send you a confirmation reminder on <strong>{{ $confirmationDate->format('M j') }}</strong>.
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <x-filament::icon
                                icon="tabler-alert-triangle"
                                class="w-5 h-5 text-warning-600 dark:text-warning-400 mt-0.5 flex-shrink-0"
                            />
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                You must confirm within 24 hours or the reservation will be cancelled.
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-2">
                        <x-filament::icon
                            icon="tabler-info-circle"
                            class="w-5 h-5 text-primary-600 dark:text-primary-400 mt-0.5 flex-shrink-0"
                        />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Payment is due at time of reservation.
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
