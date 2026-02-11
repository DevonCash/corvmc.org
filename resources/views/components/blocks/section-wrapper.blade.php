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

<section class="mb-20 {{ $bgClasses }} {{ $bg !== 'none' ? 'p-8 lg:p-12' : '' }}">
    {{ $slot }}
</section>
