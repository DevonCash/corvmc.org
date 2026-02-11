@props(['label', 'url', 'style' => 'primary'])

@php
    $btnClasses = match($style) {
        'primary' => 'btn-primary',
        'info' => 'btn-info',
        'success' => 'btn-success',
        'warning' => 'btn-warning',
        'outline-primary' => 'btn-outline btn-primary',
        'outline-secondary' => 'btn-outline btn-secondary',
        'outline-info' => 'btn-outline btn-info',
        'outline-success' => 'btn-outline btn-success',
        default => 'btn-primary',
    };
@endphp

<a href="{{ $url }}" class="btn {{ $btnClasses }} btn-lg">{{ $label }}</a>
