@props(['heading', 'description' => null, 'icon' => null])

<div class="text-center mb-12">
    @if($icon)
        <div class="flex items-center justify-center gap-3 mb-4">
            <x-icon :name="$icon" class="size-10" />
            <h2 class="text-4xl font-bold">{{ $heading }}</h2>
        </div>
    @else
        <h2 class="text-4xl font-bold mb-4">{{ $heading }}</h2>
    @endif

    @if($description)
        <p class="text-lg max-w-3xl mx-auto">{{ $description }}</p>
    @endif
</div>
