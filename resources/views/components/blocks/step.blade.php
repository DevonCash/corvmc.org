@props(['icon' => null, 'title' => null, 'description' => null, 'label' => ''])

@php $title = $title ?: $label; @endphp

<div class="card bg-base-100">
    <div class="card-body text-center">
        @if($icon)
            <x-icon :name="$icon" class="size-8 mx-auto" />
        @endif
        <h3 class="font-bold">{{ $title }}</h3>
        @if($description)
            <p class="text-sm">{{ $description }}</p>
        @endif
    </div>
</div>
