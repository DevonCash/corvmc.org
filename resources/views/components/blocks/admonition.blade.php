@props(['type' => 'note', 'content' => null])

@php
    $icon = match($type) {
        'tip' => 'tabler-flame',
        'important' => 'tabler-alert-circle',
        'warning' => 'tabler-alert-triangle',
        'caution' => 'tabler-alert-octagon',
        default => 'tabler-info-circle',
    };
@endphp

<blockquote class="md-admonition md-admonition-{{ $type }}" role="alert">
    <p class="md-admonition-title">
        <x-icon :name="$icon" class="size-5 inline-block align-text-bottom" />
        {{ ucfirst($type) }}
    </p>
    {!! $content !!}
</blockquote>
