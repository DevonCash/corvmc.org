@props(['label' => '', 'subtitle' => null])

<section>
    <div class="hero min-h-96">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                @if($label)
                    <h2 class="text-4xl font-bold mb-4">{{ $label }}</h2>
                @endif
                @if($subtitle)
                    <p class="text-lg max-w-3xl mx-auto">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
