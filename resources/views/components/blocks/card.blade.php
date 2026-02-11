@props(['icon' => null, 'heading', 'body' => null, 'features' => [], 'color' => 'base'])

@php
    $cardClasses = match($color) {
        'success' => 'bg-success text-success-content',
        'primary' => 'bg-primary text-primary-content',
        'info' => 'bg-info text-info-content',
        'warning' => 'bg-warning text-warning-content',
        'secondary' => 'bg-secondary text-secondary-content',
        'accent' => 'bg-accent text-accent-content',
        default => 'bg-base-100',
    };
@endphp

<div class="card {{ $cardClasses }} shadow-xl">
    <div class="card-body">
        @if($icon)
            <div class="flex justify-center mb-4">
                <x-icon :name="$icon" class="size-24 opacity-30" />
            </div>
        @endif

        <h4 class="card-title justify-center">{{ $heading }}</h4>

        @if($body)
            <p>{{ $body }}</p>
        @endif

        @if(count($features))
            <ul class="space-y-2 list-disc list-inside">
                @foreach($features as $feature)
                    <li>{{ $feature['text'] }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
