@props(['icon' => null, 'text' => null, 'style' => 'info', 'label' => ''])

@php $text = $text ?: $label; @endphp

<div class="alert alert-{{ $style }}">
    @if($icon)
        <x-icon :name="$icon" class="size-6" />
    @endif
    <span>{{ $text }}</span>
</div>
