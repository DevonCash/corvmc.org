<x-filament-panels::page>
    {{ $this->table }}

    <div class="mt-6">
        <x-filament::button
            type="button"
            color="gray"
            size="xl"
            tag="a"
            href="{{ \App\Filament\Kiosk\Pages\KioskDashboard::getUrl() }}"
            class="w-full sm:w-auto"
        >
            <x-filament::icon icon="heroicon-o-arrow-left" class="w-6 h-6 mr-2" />
            Back to Dashboard
        </x-filament::button>
    </div>
</x-filament-panels::page>
