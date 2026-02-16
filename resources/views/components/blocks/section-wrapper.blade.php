@props(['bg' => 'none'])

@php
    $bgClasses = match($bg) {
        'success' => 'bg-success/10',
        'primary' => 'bg-primary/20',
        'info' => 'bg-info/20',
        'warning' => 'bg-warning/20',
        'secondary' => 'bg-secondary/20',
        'accent' => 'bg-accent/20',
        default => '',
    };
@endphp

<section class="{{ $bgClasses }} {{ $bg !== 'none' ? 'px-8 py-12 lg:px-12 lg:py-16' : '' }}">
    {{ $slot }}
</section>
