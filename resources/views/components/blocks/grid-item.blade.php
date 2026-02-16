@props(['colStyle' => '--col-span:12', 'content' => null])

<div class="grid-col prose max-w-none w-full" style="{{ $colStyle }}">
    {!! $content !!}
</div>