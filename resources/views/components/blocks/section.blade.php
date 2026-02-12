@props(['class' => '', 'columns' => 2, 'fullBleed' => false, 'content' => null, 'label' => '', 'description' => null])

@php
    $gridCols = match((int) $columns) {
        1 => 'grid-cols-1',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 lg:grid-cols-2',
    };
@endphp

<section class="{{ $class }}">
    @if($fullBleed)
        <div class="hero min-h-96">
            <div class="hero-content text-center">
                <div class="max-w-2xl">
                    @if($label)
                        <h2 class="text-4xl font-bold mb-4">{{ $label }}</h2>
                        @if($description)
                            <p class="text-lg max-w-3xl mx-auto">{{ $description }}</p>
                        @endif
                    @endif
                    {!! $content !!}
                </div>
            </div>
        </div>
    @else
        @if($label)
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">{{ $label }}</h2>
                @if($description)
                    <p class="text-lg max-w-3xl mx-auto">{{ $description }}</p>
                @endif
            </div>
        @endif
        <div class="{{ $gridCols }} grid gap-6 mb-8 items-center">
            {!! $content !!}
        </div>
    @endif
</section>
