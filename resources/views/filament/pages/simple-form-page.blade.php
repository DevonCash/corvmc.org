<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        @if(method_exists($this, 'save'))
            <div class="mt-6">
                <x-filament::button type="submit">
                    Save
                </x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
