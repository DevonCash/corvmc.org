@props(['performer'])

<div class="@container bg-base-200 rounded-lg">
    <div class="flex flex-col items-center @sm:flex-row gap-4 p-4 h-full">
        @if($performer->avatar_url)
        <div class="avatar mb-2 @sm:mb-0">
            <div class="w-16 h-16 rounded-full">
                <img src="{{ $performer->avatar_url }}" alt="{{ $performer->name }}">
            </div>
        </div>
        @else
        <div class="avatar placeholder mb-2 @sm:mb-0">
            <div class="bg-neutral text-neutral-content rounded-full w-16 h-16">
                <span class="text-xl">{{ strtoupper(substr($performer->name, 0, 1)) }}</span>
            </div>
        </div>
        @endif

        <div class="flex-1 text-center @sm:text-left">
            <h3 class="font-bold text-lg">{{ $performer->name }}</h3>
            @if($performer->hometown)
            <p class="text-sm opacity-70">{{ $performer->hometown }}</p>
            @endif
            @if($performer->bio)
            <p class="text-sm mt-1">{{ Str::limit($performer->bio, 100) }}</p>
            @endif

            @if($performer->primaryLink())
                @php $link = $performer->primaryLink(); @endphp
                <div class="mt-3">
                    <a href="{{ $link['url'] }}"
                       @if($link['external']) target="_blank" @endif
                       class="btn btn-sm btn-outline btn-primary">
                        <x-unicon name="{{ $link['icon'] }}" class="size-4" />
                        {{ $link['text'] }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>