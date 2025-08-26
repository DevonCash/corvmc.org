<x-filament-panels::page>
    {{-- Alpine.js Masonry CDN --}}
    <script src="https://unpkg.com/alpinejs-masonry@1.0.16/dist/masonry.min.js" defer></script>

    <div x-data x-masonry.poll.1500 class='grid gap-5 auto-rows-max'>
        @foreach ($this->getWidgets() as $widget)
            @livewire(\Livewire\Livewire::getAlias($widget), ['lazy' => $this->isLazyWidget($widget)])
        @endforeach
    </div>

</x-filament-panels::page>
