@props(['type' => 'note', 'label' => null, 'content' => null])

@php
    $icon = match ($type) {
        'tip' => 'tabler-flame',
        'important' => 'tabler-alert-circle',
        'warning' => 'tabler-alert-triangle',
        'caution' => 'tabler-alert-octagon',
        default => 'tabler-info-circle',
    };

    $color = match ($type) {
        'tip' => 'alert-info',
        'important' => 'alert-danger',
        'warning' => 'alert-warning',
        'caution' => 'alert-warning',
        default => '',
    };
@endphp

<div class="alert flex w-full not-prose {{ $color }}" role="alert">
    <x-icon :name="$icon" class="size-5 inline-block align-text-bottom" />
    <strong>{{ $label ?? ucfirst($type) }}</strong>
    {!! $content !!}
</div>
