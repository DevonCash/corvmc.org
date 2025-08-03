<x-public.layout title="{{ $bandProfile->name }} - Local Band | Corvallis Music Collective">
    <div class="container mx-auto px-4 py-16">
        <!-- Band Header -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 mb-16">
            <!-- Band Image & Basic Info -->
            <div class="lg:col-span-1">
                <div class="card bg-base-100 shadow-xl">
                    @if($bandProfile->avatar_url)
                    <figure class="px-6 pt-6">
                        <img src="{{ $bandProfile->avatar_thumb_url }}" alt="{{ $bandProfile->name }}" class="w-full aspect-square object-cover rounded-lg" />
                    </figure>
                    @else
                    <figure class="px-6 pt-6 bg-gradient-to-r from-primary/20 to-secondary/20 aspect-square flex items-center justify-center rounded-lg">
                        <div class="text-6xl opacity-50"><x-unicon name="tabler:guitar-pick" class="size-16" /></div>
                    </figure>
                    @endif
                    
                    <div class="card-body items-center text-center">
                        <h1 class="card-title text-3xl">{{ $bandProfile->name }}</h1>
                        
                        @if($bandProfile->hometown)
                        <div class="flex items-center gap-2 text-lg opacity-70">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>{{ $bandProfile->hometown }}</span>
                        </div>
                        @endif
                        
                        @if($bandProfile->activeMembers->count() > 0)
                        <div class="badge badge-primary badge-lg">
                            {{ $bandProfile->activeMembers->count() }} {{ Str::plural('Member', $bandProfile->activeMembers->count()) }}
                        </div>
                        @endif
                        
                        @if($bandProfile->created_at)
                        <div class="text-sm opacity-50">
                            Formed {{ $bandProfile->created_at->format('F Y') }}
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Contact Card -->
                @if($bandProfile->contact && ($bandProfile->contact['visibility'] ?? 'members') !== 'private')
                <div class="card bg-base-100 shadow-lg mt-6">
                    <div class="card-body">
                        <h3 class="card-title text-lg">Get in Touch</h3>
                        
                        @if(!empty($bandProfile->contact['email']))
                        <a href="mailto:{{ $bandProfile->contact['email'] }}" class="btn btn-outline btn-primary btn-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Email
                        </a>
                        @endif
                        
                        @if(!empty($bandProfile->contact['phone']))
                        <a href="tel:{{ $bandProfile->contact['phone'] }}" class="btn btn-outline btn-secondary btn-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
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
                @if($bandProfile->bio)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title text-2xl mb-4">About {{ $bandProfile->name }}</h2>
                        <div class="prose max-w-none">
                            {!! nl2br(e($bandProfile->bio)) !!}
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Genres & Influences -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($bandProfile->genres->count() > 0)
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <h3 class="card-title">Genres</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($bandProfile->genres as $genre)
                                <span class="badge badge-primary">{{ $genre->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    @if($bandProfile->influences->count() > 0)
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <h3 class="card-title">Influences</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($bandProfile->influences as $influence)
                                <span class="badge badge-secondary">{{ $influence->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                
                <!-- Band Members -->
                @if($bandProfile->activeMembers->count() > 0)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title text-2xl mb-6">Band Members</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($bandProfile->activeMembers as $member)
                            <div class="card bg-base-200 compact">
                                <div class="card-body">
                                    <div class="flex items-center gap-4">
                                        <div class="avatar">
                                            <div class="w-16 rounded-full">
                                                <img src="{{ $member->profile->avatar_thumb_url ?? 'https://via.placeholder.com/64' }}" alt="{{ $member->name }}" />
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-bold text-lg">{{ $member->name }}</h4>
                                            @if($member->pivot->position)
                                            <p class="text-sm opacity-70">{{ $member->pivot->position }}</p>
                                            @endif
                                            @if($member->pivot->role && $member->pivot->role !== 'member')
                                            <div class="badge badge-accent badge-sm">{{ ucfirst($member->pivot->role) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    @if($member->profile && $member->profile->isVisible(auth()->user()))
                                    <div class="card-actions justify-end mt-3">
                                        <a href="{{ route('members.show', $member->profile) }}" class="btn btn-primary btn-sm">View Profile</a>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        @if($bandProfile->owner)
                        <div class="mt-6 p-4 bg-base-300 rounded-lg">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-warning" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM15.657 6.343a1 1 0 011.414 0A9.972 9.972 0 0119 12a9.972 9.972 0 01-1.929 5.657 1 1 0 11-1.414-1.414A7.971 7.971 0 0017 12c0-2.21-.895-4.21-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 12a5.983 5.983 0 01-.757 2.829 1 1 0 01-1.415-1.414A3.987 3.987 0 0013 12a3.987 3.987 0 00-.172-1.414 1 1 0 010-1.415z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm">
                                    <strong>{{ $bandProfile->owner->name }}</strong> is the primary contact for this band
                                </span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                
                <!-- Social Links -->
                @if($bandProfile->links && count($bandProfile->links) > 0)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Find Us Online</h3>
                        <div class="flex flex-wrap gap-3">
                            @foreach($bandProfile->links as $link)
                            <a href="{{ $link['url'] }}" target="_blank" class="btn btn-outline">
                                @if(str_contains(strtolower($link['name']), 'spotify'))
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.84-.179-.84-.66 0-.359.24-.66.54-.78 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.78.24 1.021zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.42 1.56-.299.421-1.02.599-1.559.3z"/>
                                </svg>
                                @elseif(str_contains(strtolower($link['name']), 'instagram'))
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
                                @elseif(str_contains(strtolower($link['name']), 'youtube'))
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                                @elseif(str_contains(strtolower($link['name']), 'facebook'))
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                @elseif(str_contains(strtolower($link['name']), 'bandcamp'))
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M0 18.75l7.437-13.5H24l-7.438 13.5H0z"/>
                                </svg>
                                @else
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                @endif
                                {{ $link['name'] }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Gallery/Media -->
                @if($bandProfile->hasMedia('gallery'))
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Gallery</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach($bandProfile->getMedia('gallery') as $media)
                            <figure>
                                <img src="{{ $media->getUrl('thumb') }}" alt="Gallery image" class="w-full h-32 object-cover rounded-lg" />
                            </figure>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Upcoming Shows -->
                @php
                $upcomingShows = \App\Models\Production::where('published_at', '<=', now())
                    ->where('start_time', '>', now())
                    ->whereHas('performers', function($query) use ($bandProfile) {
                        $query->where('band_profiles.id', $bandProfile->id);
                    })
                    ->orderBy('start_time')
                    ->limit(3)
                    ->get();
                @endphp
                
                @if($upcomingShows->count() > 0)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Upcoming Shows</h3>
                        <div class="space-y-4">
                            @foreach($upcomingShows as $show)
                            <div class="card bg-base-200 compact">
                                <div class="card-body">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-bold">{{ $show->title }}</h4>
                                            <p class="text-sm opacity-70">{{ $show->start_time->format('M j, Y g:i A') }}</p>
                                            <p class="text-sm opacity-70">{{ $show->venue_name }}</p>
                                        </div>
                                        <div class="text-right">
                                            @if($show->hasTickets())
                                            <a href="{{ $show->ticket_url }}" target="_blank" class="btn btn-primary btn-sm">Tickets</a>
                                            @endif
                                            <a href="{{ route('events.show', $show) }}" class="btn btn-outline btn-sm">Details</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Back to Directory -->
        <div class="text-center">
            <a href="{{ route('bands.index') }}" class="btn btn-outline btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Bands Directory
            </a>
        </div>
    </div>
</x-public.layout>