<x-public.layout title="Our Sponsors - Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="bg-secondary/10 py-16">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-5xl font-bold mb-6">Our Sponsors</h1>
                <p class="text-lg opacity-80">
                    The Corvallis Music Collective is proud to partner with local businesses and organizations
                    who share our commitment to supporting musicians and building community through music.
                </p>
            </div>
        </div>
    </div>

    <!-- All Sponsors - Bin-Packed Grid -->
    <div class="py-16">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 auto-rows-[190px] sm:auto-rows-[180px] gap-6 max-w-6xl mx-auto" style="grid-auto-flow: dense;">
                @php
                    $allSponsors = collect()
                        ->merge($sponsors['crescendo'])
                        ->merge($sponsors['rhythm'])
                        ->merge($sponsors['melody'])
                        ->merge($sponsors['harmony'])
                        ->merge($sponsors['in_kind']);

                    // Deterministic shuffle based on sponsor IDs
                    $sponsorIds = $allSponsors->pluck('id')->sort()->implode(',');
                    mt_srand(crc32($sponsorIds));
                    $allSponsors = $allSponsors->shuffle();
                    mt_srand(); // Reset random seed
                @endphp

                @foreach ($allSponsors as $sponsor)
                    @php
                        // Define grid spans for each tier
                        $gridClass = match($sponsor->tier) {
                            'crescendo' => 'col-span-2 row-span-2', // 2x2
                            'rhythm' => 'col-span-2 row-span-1',    // 2x1
                            default => 'col-span-1 row-span-1',     // 1x1
                        };
                    @endphp
                    <div class="{{ $gridClass }}">
                        @if ($sponsor->tier === 'crescendo')
                            {{-- Crescendo: 2x2 tiles --}}
                            <div class="bg-white shadow-xl w-full h-full flex flex-col p-3">
                                <div class="bg-base-200 flex-1 flex items-center justify-center mb-2 p-2">
                                    @if ($sponsor->getFirstMediaUrl('logo'))
                                        <img src="{{ $sponsor->getFirstMediaUrl('logo') }}"
                                             alt="{{ $sponsor->name }}"
                                             class="w-full h-full object-contain">
                                    @else
                                        <div class="text-3xl font-bold text-center">{{ $sponsor->name }}</div>
                                    @endif
                                </div>
                                <div class="text-center shrink-0">
                                    <p class="font-semibold text-sm">{{ $sponsor->name }}</p>
                                </div>
                            </div>
                        @elseif ($sponsor->tier === 'rhythm')
                            {{-- Rhythm: 2x1 tiles - horizontal orientation --}}
                            <div class="bg-white shadow-lg w-full h-full flex grow flex-row p-2">
                                <div class="bg-base-200 flex items-center justify-center mr-2 p-2">
                                    @if ($sponsor->getFirstMediaUrl('logo'))
                                        <img src="{{ $sponsor->getFirstMediaUrl('logo') }}"
                                             alt="{{ $sponsor->name }}"
                                             class="w-full h-full object-contain">
                                    @else
                                        <div class="text-xl font-bold text-center">{{ $sponsor->name }}</div>
                                    @endif
                                </div>
                                <div class="flex items-center justify-center flex-1 shrink-0">
                                    <p class="font-semibold text-sm text-center">{{ $sponsor->name }}</p>
                                </div>
                            </div>
                        @elseif (in_array($sponsor->tier, ['melody', 'in_kind']))
                            {{-- Melody/In-Kind: 1x1 tile --}}
                            <div class="bg-white shadow w-full h-full flex flex-col p-2">
                                <div class="bg-base-200 flex-1 flex items-center justify-center mb-1 p-1">
                                    @if ($sponsor->getFirstMediaUrl('logo'))
                                        <img src="{{ $sponsor->getFirstMediaUrl('logo') }}"
                                             alt="{{ $sponsor->name }}"
                                             class="w-full h-full object-contain">
                                    @else
                                        <div class="text-xs font-bold text-center">{{ $sponsor->name }}</div>
                                    @endif
                                </div>
                                <div class="text-center flex-shrink-0">
                                    <p class="font-semibold text-[10px] leading-tight">{{ $sponsor->name }}</p>
                                </div>
                            </div>
                        @else
                            {{-- Harmony: 1x1 tile --}}
                            <div class="bg-white shadow w-full h-full flex flex-col p-1.5">
                                <div class="bg-base-200 flex-1 flex items-center justify-center mb-1 p-1">
                                    @if ($sponsor->getFirstMediaUrl('logo'))
                                        <img src="{{ $sponsor->getFirstMediaUrl('logo') }}"
                                             alt="{{ $sponsor->name }}"
                                             class="w-full h-full object-contain">
                                    @else
                                        <div class="text-[8px] font-bold text-center">{{ $sponsor->name }}</div>
                                    @endif
                                </div>
                                <div class="text-center flex-shrink-0">
                                    <p class="font-semibold text-[8px] leading-tight">{{ $sponsor->name }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Become a Sponsor CTA -->
    <div class="bg-primary text-primary-content py-16">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto">
                <h2 class="text-4xl font-bold mb-6">Become a Sponsor</h2>
                <p class="text-lg mb-8 opacity-90">
                    Partner with us to support local musicians and build community through music.
                    Our sponsorship program offers multiple tiers with benefits designed to
                    promote your business while making a real impact in the Corvallis music scene.
                </p>
                <a href="{{ route('contact') }}" class="btn btn-secondary btn-lg">
                    Learn About Sponsorship
                </a>
            </div>
        </div>
    </div>
</x-public.layout>
