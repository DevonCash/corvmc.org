<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search Bar -->
        <div class="w-full">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search members by name, bio, or contact info..."
                    class="w-full"
                />
            </x-filament::input.wrapper>
        </div>

        <!-- Filters Form -->
        <x-filament::section heading='Filters' collapsible :collapsed="$filtersCollapsed" wire:click="$toggle('filtersCollapsed')">
            {{ $this->filtersForm }}

            <x-slot name='afterHeader'>
                <x-filament::button wire:click="clearFilters" color="gray" size="sm">
                    Clear Filters
                </x-filament::button>
            </x-slot>
        </x-filament::section>

        <!-- Member Grid -->
        <div class="relative">
            <!-- Loading Spinner -->
            <div wire:loading.delay.long class="absolute inset-0 bg-white/75 flex items-center justify-center z-10">
                <div class="flex flex-col items-center gap-2">
                    <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-600"></div>
                    <span class="text-sm text-gray-600">Loading members...</span>
                </div>
            </div>

            <div class="grid gap-6"
                style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); max-width: calc(4 * (280px + 1.5rem) - 1.5rem);">
                @forelse ($this->getMembers() as $member)
                    <x-membership::member-card :member="$member" :link="route('filament.member.directory.resources.members.view', $member)" />
                @empty
                    <div class="col-span-full text-center py-12 space-y-2">
                        <x-tabler-user-question class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No member profiles found</h3>
                        <p class="mt-1 text-sm text-gray-500">No members found matching your criteria – check back soon!
                        </p>
                        <x-filament::button wire:click="clearFilters" color="gray" size="sm">
                            Clear Filters
                        </x-filament::button>
                    </div>
                @endforelse
            </div>

            @if ($this->getMembers()->hasPages())
                <div class="mt-6">
                    {{ $this->getMembers()->links() }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
