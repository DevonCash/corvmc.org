@props(['content', 'alertIcon' => null, 'alertText' => null, 'alertStyle' => 'info'])

<div>
    <div class="prose max-w-none">
        {!! Str::markdown($content) !!}
    </div>

    @if($alertText)
        <div class="alert alert-{{ $alertStyle }} mt-4">
            @if($alertIcon)
                <x-icon :name="$alertIcon" class="size-6" />
            @endif
            <span>{!! Str::inlineMarkdown($alertText) !!}</span>
        </div>
    @endif
</div>
