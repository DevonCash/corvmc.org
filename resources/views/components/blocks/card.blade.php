@props(['icon' => null, 'heading' => null, 'body' => null, 'color' => 'base', 'content' => null, 'label' => ''])

@php
    $heading = $heading ?: $label;

    $cardClasses = match($color) {
        'success' => 'bg-success text-success-content',
        'primary' => 'bg-primary text-primary-content',
        'info' => 'bg-info text-info-content',
        'warning' => 'bg-warning text-warning-content',
        'secondary' => 'bg-secondary text-secondary-content',
        'accent' => 'bg-accent text-accent-content',
        default => 'bg-base-100',
    };

    $proseColor = $color !== 'base'
        ? '[--tw-prose-body:inherit] [--tw-prose-headings:inherit] [--tw-prose-bold:inherit] [--tw-prose-bullets:inherit] [--tw-prose-counters:inherit] [--tw-prose-th-borders:inherit] [--tw-prose-td-borders:inherit]'
        : '';
@endphp

<div class="card {{ $cardClasses }} shadow-xl">
    <div class="card-body">
        @if($heading)
            <div class="flex items-center gap-3">
                @if($icon)
                    <x-icon :name="$icon" class="size-6 opacity-70" />
                @endif
                <h4 class="card-title">{{ $heading }}</h4>
            </div>
        @endif

        @if($content)
            <div class="prose max-w-none {{ $proseColor }}">
                {!! $content !!}
            </div>
        @elseif($body)
            <div class="prose max-w-none {{ $proseColor }}">
                {!! Str::markdown($body, extensions: [new \Zenstruck\CommonMark\Extension\GitHub\AdmonitionExtension()]) !!}
            </div>
        @endif
    </div>
</div>
