@php
    $activities = $getState();
    $eventColors = [
        'created' => 'bg-green-500',
        'confirmed' => 'bg-green-500',
        'cancelled' => 'bg-red-500',
        'auto_cancelled' => 'bg-red-500',
        'rescheduled' => 'bg-amber-500',
        'payment_recorded' => 'bg-green-500',
        'comped' => 'bg-blue-500',
        'updated' => 'bg-blue-500',
        'deleted' => 'bg-red-500',
    ];
@endphp

<div class="space-y-0">
    @forelse ($activities as $activity)
        <div class="flex gap-3 py-3 {{ !$loop->last ? 'border-b border-gray-200 dark:border-gray-700' : '' }}">
            <div class="flex flex-col items-center">
                <div class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $eventColors[$activity->event] ?? 'bg-gray-400' }}"></div>
                @unless($loop->last)
                    <div class="w-px grow bg-gray-200 dark:bg-gray-700"></div>
                @endunless
            </div>
            <div class="min-w-0 flex-1 pb-1">
                <p class="text-sm text-gray-900 dark:text-gray-100">
                    {{ $activity->description }}
                </p>
                <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ $activity->causer?->name ?? 'System' }}</span>
                    <span>&middot;</span>
                    <span title="{{ $activity->created_at->format('M j, Y g:i A') }}">
                        {{ $activity->created_at->diffForHumans() }}
                    </span>
                </div>
            </div>
        </div>
    @empty
        <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
            No activity recorded yet.
        </p>
    @endforelse
</div>
