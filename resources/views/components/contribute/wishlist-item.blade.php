@props(['icon', 'title', 'price', 'description', 'color' => 'primary', 'priority' => false])

<div class="card bg-base-100 shadow-lg {{ $priority ? 'border-l-4 border-error' : '' }}">
    <div class="card-body">
        <div class="flex items-center gap-3 mb-4">
            <x-unicon :name="$icon" class="size-8 text-{{ $color }}" />
            <h4 class="card-title text-lg">{{ $title }}</h4>
        </div>
        {{-- <div class="text-2xl font-bold text-{{ $color }} mb-2">{{ $price }}</div> --}}
        <p class="text-sm opacity-70">{{ $description }}</p>
    </div>
</div>
