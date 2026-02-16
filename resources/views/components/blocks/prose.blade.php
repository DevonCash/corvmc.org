@props(['content'])

<div class="prose max-w-none">
    {!! Str::markdown($content, extensions: [new \Zenstruck\CommonMark\Extension\GitHub\AdmonitionExtension()]) !!}
</div>
