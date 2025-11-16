<x-filament-widgets::widget>
    <div class="flex items-center justify-between gap-4 px-4 py-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 -mb-4">
        <div class="flex items-center gap-3">
            <x-filament::icon
                icon="tabler-clock-hour-4"
                class="size-5 text-{{ $this->isSustainingMember ? 'success-500' : 'gray-400' }}"
            />

            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ number_format($this->remainingHours, 1) }}
                </span>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    free hours remaining
                </span>
            </div>
        </div>

        <div class="text-sm text-gray-600 dark:text-gray-400 text-right">
            @if ($this->isSustainingMember)
                @if ($this->totalAllocatedHours > 0)
                    <div>
                        @if ($this->usedHours > 0)
                            {{ number_format($this->usedHours, 1) }}/{{ number_format($this->totalAllocatedHours, 1) }} hours used this month
                        @else
                            {{ number_format($this->totalAllocatedHours, 1) }} hours available this month
                        @endif
                    </div>
                    @if ($this->allocationDate)
                        <div class="text-xs text-gray-500 dark:text-gray-500">
                            Allocated {{ $this->allocationDate }}
                        </div>
                    @endif
                @endif
            @else
                <a href="{{ route('filament.member.pages.membership') }}" class="text-primary-600 hover:underline dark:text-primary-400">
                    Learn More
                </a>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
