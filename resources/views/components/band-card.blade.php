@props(['item'])

@php
    $band = $item;
@endphp

<a href="{{ route('bands.show', $band) }}" class="block group vinyl-card">
    <div
        class="relative aspect-square max-w-sm mx-auto bg-black shadow-lg hover:shadow-xl transition-all duration-300 group-hover:scale-[1.02] group-hover:-rotate-1 group-hover:-translate-x-2">

        <!-- Vinyl Record (behind the sleeve) -->
        <div
            class="vinyl-record absolute top-0 left-0 w-full h-full transform translate-x-0 transition-transform duration-700 group-hover:translate-x-3/8 pointer-events-none">
            <div class="relative w-full h-full flex items-center justify-center ">
                <!-- Vinyl Record Disc -->
                <div
                    class="w-5/6 h-5/6 bg-white rounded-full flex items-center justify-center shadow-2xl overflow-hidden">
                    <!-- Record Grooves -->
                    <div class="absolute inset-[8%] rounded-full bg-gradient-to-br from-gray-900 to-black"></div>
                    <div class="absolute inset-[8%] border border-gray-700 rounded-full opacity-40"></div>
                    <div class="absolute inset-[16%] border border-gray-600 rounded-full opacity-35"></div>
                    <div class="absolute inset-[24%] border border-gray-500 rounded-full opacity-30"></div>
                    <div class="absolute inset-[32%] border border-gray-400 rounded-full opacity-25"></div>
                    <div class="absolute inset-[40%] border border-gray-300 rounded-full opacity-20"></div>

                    <!-- Center Label -->
                    <div
                        class="w-20 h-20 z-10 bg-red-800 rounded-full flex items-center justify-center border-2 border-red-900">
                        <x-logo :soundLines="false" class="h-8 text-white" />
                    </div>
                </div>
            </div>
        </div>
        <!-- Album Cover Art -->
        <div class="relative w-full h-full">
            @if ($band->avatar_url)
                <img src="{{ $band->avatar_url }}" alt="{{ $band->name }}" class="w-full h-full object-cover">
                <!-- Dark overlay for text readability -->
                <div class="absolute inset-0 bg-black/40"></div>
            @else
                <!-- Default album cover design -->
                <div class="w-full h-full bg-base-300 flex items-center justify-center">
                    <x-unicon name="tabler:guitar-pick" class="size-24 text-base-content/20" />
                </div>
                <div class="absolute inset-0 bg-black/30"></div>
            @endif
        </div>

        <!-- Album Info Overlay -->
        <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
            <!-- Band Name (Artist) -->
            <h2 class="font-bold text-lg leading-tight mb-3 drop-shadow-lg">{{ $band->name }}</h2>

            <!-- Genre/Location Info -->
            <div class="flex items-center justify-between text-xs opacity-80">
                <div class="flex items-center gap-2">
                    @if ($band->genres->count() > 0)
                        @if ($band->genres->count() == 1)
                            <span>{{ $band->genres->first()->name }}</span>
                        @elseif ($band->genres->count() >= 2)
                            <span>{{ $band->genres->take(2)->pluck('name')->join(' • ') }}</span>
                            @if ($band->genres->count() > 2)
                                <span>+{{ $band->genres->count() - 2 }}</span>
                            @endif
                        @endif
                    @endif
                </div>

                @if ($band->hometown)
                    <div class="flex items-center gap-1">
                        <x-unicon name="tabler:map-pin" class="size-3" />
                        <span>{{ $band->hometown }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Record Label (CMC) -->
        <div class="absolute top-4 right-4">
            <div class="bg-black/90 backdrop-blur-sm px-2 py-1 rounded">
                <x-logo-mono color="white" class="h-5" />
            </div>
        </div>

    </div>
</a>
