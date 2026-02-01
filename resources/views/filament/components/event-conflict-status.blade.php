@php
    use Carbon\Carbon;

    $conflictStatus = $get('conflict_status');
    $conflictDataJson = $get('conflict_data');
    $conflictData = $conflictDataJson ? json_decode($conflictDataJson, true) : null;
    $forceOverride = $get('force_override');
    $isAdmin = auth()->user()?->hasRole('admin');

    // Determine display state
    $state = match($conflictStatus) {
        'available' => 'success',
        'setup_conflict' => 'warning',
        'event_conflict' => 'danger',
        default => 'neutral',
    };

    $icon = match($state) {
        'success' => 'tabler-circle-check',
        'warning' => 'tabler-alert-triangle',
        'danger' => 'tabler-alert-circle',
        default => 'tabler-clock',
    };

    $title = match($conflictStatus) {
        'available' => 'Space is available',
        'setup_conflict' => 'Setup/teardown time has conflicts',
        'event_conflict' => 'Event time has conflicts',
        default => 'Checking availability...',
    };

    $description = match($conflictStatus) {
        'available' => 'The practice space is available for your event. A reservation will be created automatically.',
        'setup_conflict' => 'The event time is available, but the setup or teardown period overlaps with existing reservations. You can adjust the setup/teardown times below, or proceed with reduced buffer time.',
        'event_conflict' => $isAdmin
            ? 'The event time directly conflicts with existing reservations. As an admin, you can override this conflict.'
            : 'The event time directly conflicts with existing reservations. Please go back and choose a different time.',
        default => 'Please wait while we check for conflicts...',
    };

    $bgColor = match($state) {
        'success' => 'bg-success-50 dark:bg-success-900/20 border-success-200 dark:border-success-800',
        'warning' => 'bg-warning-50 dark:bg-warning-900/20 border-warning-200 dark:border-warning-800',
        'danger' => 'bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800',
        default => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
    };

    $iconColor = match($state) {
        'success' => 'text-success-600 dark:text-success-400',
        'warning' => 'text-warning-600 dark:text-warning-400',
        'danger' => 'text-danger-600 dark:text-danger-400',
        default => 'text-gray-600 dark:text-gray-400',
    };

    $titleColor = match($state) {
        'success' => 'text-success-800 dark:text-success-200',
        'warning' => 'text-warning-800 dark:text-warning-200',
        'danger' => 'text-danger-800 dark:text-danger-200',
        default => 'text-gray-800 dark:text-gray-200',
    };
@endphp

<div class="rounded-lg border-2 {{ $bgColor }} p-4 mb-4">
    <div class="flex items-start gap-3">
        <x-filament::icon
            :icon="$icon"
            class="w-6 h-6 {{ $iconColor }} flex-shrink-0 mt-0.5"
        />
        <div class="flex-1">
            <h4 class="font-semibold {{ $titleColor }}">{{ $title }}</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $description }}</p>

            {{-- Show conflict details --}}
            @if($conflictData && ($conflictStatus === 'event_conflict' || $conflictStatus === 'setup_conflict'))
                @php
                    $conflictsToShow = $conflictStatus === 'event_conflict'
                        ? $conflictData['event_conflicts']
                        : $conflictData['setup_conflicts'];
                @endphp

                <div class="mt-3 space-y-2">
                    {{-- Reservations --}}
                    @if(!empty($conflictsToShow['reservations']))
                        <div class="text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Conflicting Reservations:</span>
                            <ul class="mt-1 space-y-1">
                                @foreach($conflictsToShow['reservations'] as $reservation)
                                    <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                        <x-filament::icon icon="tabler-calendar-event" class="w-4 h-4" />
                                        <span class="font-medium">{{ $reservation['display_title'] ?? 'Unknown' }}</span>
                                        <span class="text-gray-500">
                                            {{ Carbon::parse($reservation['reserved_at'])->format('g:i A') }} -
                                            {{ Carbon::parse($reservation['reserved_until'])->format('g:i A') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Productions/Events --}}
                    @if(!empty($conflictsToShow['productions']))
                        <div class="text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Conflicting Events:</span>
                            <ul class="mt-1 space-y-1">
                                @foreach($conflictsToShow['productions'] as $production)
                                    <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                        <x-filament::icon icon="tabler-music" class="w-4 h-4" />
                                        {{ $production['title'] }}
                                        ({{ Carbon::parse($production['start_datetime'])->format('g:i A') }})
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Closures --}}
                    @if(!empty($conflictsToShow['closures']))
                        <div class="text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Space Closures:</span>
                            <ul class="mt-1 space-y-1">
                                @foreach($conflictsToShow['closures'] as $closure)
                                    <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                        <x-filament::icon icon="tabler-lock" class="w-4 h-4" />
                                        {{ $closure['reason'] }}
                                        ({{ Carbon::parse($closure['starts_at'])->format('g:i A') }} -
                                        {{ Carbon::parse($closure['ends_at'])->format('g:i A') }})
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Override indicator --}}
            @if($forceOverride && ($conflictStatus === 'event_conflict' || $conflictStatus === 'setup_conflict'))
                <div class="mt-3 flex items-center gap-2 text-sm text-warning-700 dark:text-warning-300 bg-warning-100 dark:bg-warning-900/30 rounded px-3 py-2">
                    <x-filament::icon icon="tabler-shield-check" class="w-4 h-4" />
                    <span class="font-medium">Admin override enabled - conflicts will be ignored</span>
                </div>
            @endif
        </div>
    </div>
</div>
