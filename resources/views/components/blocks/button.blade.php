@props(['label' => '', 'url', 'color' => 'primary', 'variant' => 'solid'])

@php
    $btnColor = match($color) {
        'secondary' => 'btn-secondary',
        'info' => 'btn-info',
        'success' => 'btn-success',
        'warning' => 'btn-warning',
        default => 'btn-primary',
    };
    $btnVariant = $variant === 'outline' ? 'btn-outline' : '';
@endphp

<a href="{{ $url }}" class="btn {{ $btnColor }} {{ $btnVariant }} btn-lg">{{ $label }}</a>
