@props(['icon' => null, 'text', 'style' => 'info'])

<div class="alert alert-{{ $style }}">
    @if($icon)
        <x-icon :name="$icon" class="size-6" />
    @endif
    <span>{{ $text }}</span>
</div>
