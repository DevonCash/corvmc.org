<x-public.layout title="{{ $memberProfile->user->name }} - Local Musician | Corvallis Music Collective">
    <div class="container mx-auto px-4 py-16">
        <!-- Profile Header -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 mb-16">
            <!-- Profile Image & Basic Info -->
            <div class="lg:col-span-1">
                <div class="card bg-base-100 shadow-xl">
                    <figure class="px-6 pt-6">
                        <div class="avatar">
                            <div class="w-48 rounded-full">
                                <img src="{{ $memberProfile->avatar_thumb_url }}" alt="{{ $memberProfile->user->name }}" />
                            </div>
                        </div>
                    </figure>
                    
                    <div class="card-body items-center text-center">
                        <h1 class="card-title text-3xl">{{ $memberProfile->user->name }}</h1>
                        
                        @if($memberProfile->hometown)
                        <div class="flex items-center gap-2 text-lg opacity-70">
                            <x-unicon name="tabler:map-pin" class="size-5" />
                            <span>{{ $memberProfile->hometown }}</span>
                        </div>
                        @endif
                        
                        @if($memberProfile->user->created_at)
                        <div class="text-sm opacity-50">
                            Member since {{ $memberProfile->user->created_at->format('F Y') }}
                        </div>
                        @endif

                        <!-- Member Flags -->
                        @if($memberProfile->getActiveFlagsWithLabels())
                        <div class="flex flex-wrap gap-2 mt-4">
                            @foreach($memberProfile->getActiveFlagsWithLabels() as $flag => $label)
                            <span class="badge badge-accent badge-sm">{{ $label }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Contact Card -->
                @if($memberProfile->contact && ($memberProfile->contact['visibility'] ?? 'members') !== 'private')
                <div class="card bg-base-100 shadow-lg mt-6">
                    <div class="card-body">
                        <h3 class="card-title text-lg">Get in Touch</h3>
                        
                        @if(!empty($memberProfile->contact['email']))
                        <a href="mailto:{{ $memberProfile->contact['email'] }}" class="btn btn-outline btn-primary btn-sm">
                            <x-unicon name="tabler:mail" class="size-4 mr-2" />
                            Email
                        </a>
                        @endif
                        
                        @if(!empty($memberProfile->contact['phone']))
                        <a href="tel:{{ $memberProfile->contact['phone'] }}" class="btn btn-outline btn-secondary btn-sm">
                            <x-unicon name="tabler:phone" class="size-4 mr-2" />
                            Call
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Bio -->
                @if($memberProfile->bio)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title text-2xl mb-4">About</h2>
                        <div class="prose max-w-none">
                            {!! nl2br(e($memberProfile->bio)) !!}
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Skills & Genres -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($memberProfile->skills && count($memberProfile->skills) > 0)
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <h3 class="card-title">Skills & Instruments</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($memberProfile->skills as $skill)
                                <span class="badge badge-primary">{{ $skill }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    @if($memberProfile->genres && count($memberProfile->genres) > 0)
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <h3 class="card-title">Genres</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($memberProfile->genres as $genre)
                                <span class="badge badge-secondary">{{ $genre }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                
                <!-- Influences -->
                @if($memberProfile->influences && count($memberProfile->influences) > 0)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Musical Influences</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($memberProfile->influences as $influence)
                            <span class="badge badge-accent">{{ $influence }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Social Links -->
                @if($memberProfile->links && count($memberProfile->links) > 0)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Links</h3>
                        <div class="flex flex-wrap gap-3">
                            @foreach($memberProfile->links as $link)
                            <a href="{{ $link['url'] }}" target="_blank" class="btn btn-outline btn-sm">
                                @if(str_contains(strtolower($link['name']), 'spotify'))
                                <x-unicon name="tabler:brand-spotify" class="size-4 mr-2" />
                                @elseif(str_contains(strtolower($link['name']), 'instagram'))
                                <x-unicon name="tabler:brand-instagram" class="size-4 mr-2" />
                                @elseif(str_contains(strtolower($link['name']), 'youtube'))
                                <x-unicon name="tabler:brand-youtube" class="size-4 mr-2" />
                                @elseif(str_contains(strtolower($link['name']), 'facebook'))
                                <x-unicon name="tabler:brand-facebook" class="size-4 mr-2" />
                                @else
                                <x-unicon name="tabler:external-link" class="size-4 mr-2" />
                                @endif
                                {{ $link['name'] }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Bands -->
                @if($memberProfile->user->bandProfiles->count() > 0)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Bands</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($memberProfile->user->bandProfiles as $band)
                            <div class="card bg-base-200 compact">
                                <div class="card-body">
                                    <div class="flex items-center gap-3">
                                        @if($band->avatar_url)
                                        <div class="avatar">
                                            <div class="w-12 rounded-full">
                                                <img src="{{ $band->avatar_thumb_url }}" alt="{{ $band->name }}" />
                                            </div>
                                        </div>
                                        @endif
                                        <div>
                                            <h4 class="font-bold">{{ $band->name }}</h4>
                                            @if($band->pivot->position)
                                            <p class="text-sm opacity-70">{{ $band->pivot->position }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="card-actions justify-end">
                                        <a href="{{ route('bands.show', $band) }}" class="btn btn-primary btn-sm">View Band</a>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Gallery/Media -->
                @if($memberProfile->hasMedia('gallery'))
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Gallery</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach($memberProfile->getMedia('gallery') as $media)
                            <figure>
                                <img src="{{ $media->getUrl('thumb') }}" alt="Gallery image" class="w-full h-32 object-cover rounded-lg" />
                            </figure>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Back to Directory -->
        <div class="text-center">
            <a href="{{ route('members.index') }}" class="btn btn-outline btn-primary">
                <x-unicon name="tabler:arrow-left" class="size-4 mr-2" />
                Back to Members Directory
            </a>
        </div>
    </div>
</x-public.layout>