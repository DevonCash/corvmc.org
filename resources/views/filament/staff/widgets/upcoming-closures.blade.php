<x-filament-widgets::widget>
    <div class="flex items-start gap-3 p-3 rounded-lg bg-danger-50 dark:bg-danger-950/20 border border-danger-200 dark:border-danger-800">
        <x-filament::icon
            icon="tabler-alert-triangle"
            class="h-5 w-5 text-danger-500 shrink-0 mt-0.5"
        />
        <div class="flex-1 min-w-0">
            <h4 class="text-sm font-medium text-danger-800 dark:text-danger-200">
                Space Closures This Week
            </h4>
            <ul class="mt-2 space-y-1">
                @foreach ($this->getClosures() as $closure)
                    <li class="text-sm text-danger-700 dark:text-danger-300">
                        <a
                            href="{{ $this->getClosureUrl($closure) }}"
                            class="hover:underline"
                        >
                            <span class="font-medium">{{ $closure->type->getLabel() }}</span>
                            &mdash;
                            {{ $closure->starts_at->format('D, M j') }}
                            @if (!$closure->starts_at->isSameDay($closure->ends_at))
                                - {{ $closure->ends_at->format('D, M j') }}
                            @else
                                ({{ $closure->starts_at->format('g:i A') }} - {{ $closure->ends_at->format('g:i A') }})
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-filament-widgets::widget>
