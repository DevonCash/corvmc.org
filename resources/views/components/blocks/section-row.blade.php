@props(['columns' => 2, 'fullBleed' => false])

@if($fullBleed)
    <div class="hero min-h-96">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                {{ $slot }}
            </div>
        </div>
    </div>
@else
    @php
        $gridCols = match((int) $columns) {
            1 => 'grid-cols-1',
            3 => 'grid-cols-1 md:grid-cols-3',
            4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
            default => 'grid-cols-1 lg:grid-cols-2',
        };
    @endphp
    <div class="{{ $gridCols }} grid gap-6 mb-8 items-center">
        {{ $slot }}
    </div>
@endif
