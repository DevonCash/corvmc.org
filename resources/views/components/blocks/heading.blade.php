@props(['icon' => null, 'description' => null, 'label' => ''])

<hgroup class="not-prose">
    <div class="flex items-center gap-3">
        @if($icon)
            <x-icon :name="$icon" class="size-8 opacity-70" />
        @endif
        <h3 class="text-2xl font-bold">{{ $label }}</h3>
    </div>
    @if($description)
        <p class="text-lg opacity-70 mt-1">{{ $description }}</p>
    @endif
</hgroup>
