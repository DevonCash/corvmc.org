@props(['label' => '', 'value', 'subtitle' => null, 'color' => 'base'])

@php
    $cardClasses = match($color) {
        'primary' => 'bg-primary text-primary-content',
        'info' => 'bg-info text-info-content',
        'success' => 'bg-success text-success-content',
        'warning' => 'bg-warning text-warning-content',
        default => 'bg-base-100',
    };

    $valueClasses = match($color) {
        'base' => 'text-primary',
        default => '',
    };
@endphp

<div class="card {{ $cardClasses }} shadow-lg">
    <div class="card-body text-center">
        <h4 class="card-title justify-center">{{ $label }}</h4>
        <div class="text-2xl font-bold {{ $valueClasses }}">{{ $value }}</div>
        @if($subtitle)
            <p class="text-sm">{{ $subtitle }}</p>
        @endif
    </div>
</div>
