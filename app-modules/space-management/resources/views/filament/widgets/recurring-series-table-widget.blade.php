<x-filament-widgets::widget>
    @if ($this->isSustainingMember)
        {{ $this->table }}
    @else
        <x-filament::section>
            <div class=" flex flex-col items-center gap-3">
                <div class="flex items-center w-full gap-3 flex-col sm:flex-row ">
                    <div class="rounded-full bg-primary/20 p-2">
                        <x-filament::icon icon="tabler-repeat" class="size-6 text-primary" />
                    </div>

                    <h3 class="text-lg grow font-semibold text-gray-950 dark:text-white">
                        Recurring Reservations
                    </h3>

                    <x-filament::button class="hidden sm:flex" :href="route('filament.member.pages.membership')" icon="heroicon-o-star" tag="a">
                        Learn More
                    </x-filament::button>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400 text-center sm:text-left">
                    Recurring reservations allow you to automatically book the practice space on a regular schedule
                    (e.g., every Tuesday at 7pm). This feature is available to sustaining members.
                </p>
                <x-filament::button class="sm:hidden " :href="route('filament.member.pages.membership')" icon="heroicon-o-star" tag="a">
                    Learn More
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
