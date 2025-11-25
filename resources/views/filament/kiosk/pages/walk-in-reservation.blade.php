<x-filament-panels::page>
    <form wire:submit="create">
        {{ $this->form }}

        <div class="mt-6 flex gap-4">
            <x-filament::button
                type="submit"
                size="xl"
                class="flex-1"
            >
                <x-filament::icon icon="heroicon-o-check" class="w-6 h-6 mr-2" />
                Create Reservation
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                size="xl"
                tag="a"
                href="{{ \App\Filament\Kiosk\Pages\KioskDashboard::getUrl() }}"
            >
                <x-filament::icon icon="heroicon-o-x-mark" class="w-6 h-6 mr-2" />
                Cancel
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
