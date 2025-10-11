@php
    use App\Models\User;
    use Carbon\Carbon;
    use App\Actions\Reservations\CalculateReservationCost;

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
            $startFormatted = Carbon::parse($start)->format('l, M j, Y \\a\\t g:i A');
            $endFormatted = Carbon::parse($end)->format('g:i A');
            $duration = Carbon::parse($start)->diffInMinutes(Carbon::parse($end)) / 60;

            $calculation = CalculateReservationCost::run(
                $user,
                Carbon::parse($start),
                Carbon::parse($end)
            );

            $reservationDate = Carbon::parse($start);
            $paidHours = $calculation['total_hours'] - $calculation['free_hours'];
        }
    }
@endphp

@if(!$showSummary)
    <div class="text-gray-500 text-sm">
        Complete previous step to see summary
    </div>
@elseif(!$user)
    <div class="text-red-600 text-sm">
        User not found
    </div>
@else
    <div class="space-y-4">
        {{-- Date and Time --}}
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <div class="flex items-start gap-3">
                <div class="text-2xl">📅</div>
                <div class="flex-1">
                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ $startFormatted }} - {{ $endFormatted }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Duration and Cost --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
                <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                    <span class="text-xl">⏱️</span>
                    <span class="font-medium">Duration</span>
                </div>
                <div class="mt-1 text-2xl font-bold text-blue-900 dark:text-blue-100">
                    {{ number_format($duration, 1) }} hours
                </div>
            </div>

            <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-4">
                <div class="flex items-center gap-2 text-green-700 dark:text-green-300">
                    <span class="text-xl">💰</span>
                    <span class="font-medium">Total Cost</span>
                </div>
                <div class="mt-1 text-2xl font-bold text-green-900 dark:text-green-100">
                    {{ $calculation['cost']->formatTo('en_US') }}
                </div>
            </div>
        </div>

        {{-- Free and Paid Hours --}}
        @if($calculation['free_hours'] > 0 || $paidHours > 0)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-2">
                @if($calculation['free_hours'] > 0)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <span class="text-lg">🎁</span>
                            <span>Free hours</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($calculation['free_hours'], 1) }}
                        </span>
                    </div>
                @endif

                @if($paidHours > 0)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <span class="text-lg">💳</span>
                            <span>Paid hours</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($paidHours, 1) }}
                        </span>
                    </div>
                @endif
            </div>
        @endif

        {{-- Recurring Badge --}}
        @if($isRecurring)
            <div class="rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 p-3">
                <div class="flex items-center gap-2 text-purple-700 dark:text-purple-300">
                    <span class="text-lg">🔄</span>
                    <span class="font-medium">Recurring weekly reservation</span>
                </div>
            </div>
        @endif

        {{-- Notes --}}
        @if($notes)
            <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-4">
                <div class="flex items-start gap-2">
                    <span class="text-lg">📝</span>
                    <div class="flex-1">
                        <div class="font-medium text-amber-800 dark:text-amber-200 mb-1">Notes</div>
                        <div class="text-amber-900 dark:text-amber-100 text-sm whitespace-pre-wrap">{{ $notes }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Confirmation Process Info --}}
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
            @if($isRecurring)
                <div class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                    <span class="text-lg">📋</span>
                    <span class="text-sm">This recurring reservation requires manual approval.</span>
                </div>
            @elseif($reservationDate->isAfter(Carbon::now()->addWeek()))
                @php
                    $confirmationDate = $reservationDate->copy()->subDays(3);
                @endphp
                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    <div class="flex items-start gap-2">
                        <span class="text-lg">📧</span>
                        <div>
                            <div>We'll send you a confirmation reminder on <strong>{{ $confirmationDate->format('M j') }}</strong>.</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 text-amber-700 dark:text-amber-300">
                        <span class="text-lg">⚠️</span>
                        <div>You must confirm within 24 hours or the reservation will be cancelled.</div>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-2 text-green-700 dark:text-green-300">
                    <span class="text-lg">✅</span>
                    <span class="text-sm">This reservation will be immediately confirmed.</span>
                </div>
            @endif
        </div>
    </div>
@endif
