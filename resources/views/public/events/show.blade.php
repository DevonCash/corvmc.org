<x-public.layout :title="$production->title . ' | Corvallis Music Collective'">
    <!-- Hero Section with Event Image -->
    <div class="hero min-h-96 bg-gradient-to-r from-secondary/10 to-accent/10 relative overflow-hidden">
        @if($production->poster_url)
        <div class="absolute inset-0 bg-black/50 z-10"></div>
        <img src="{{ $production->poster_url }}" alt="{{ $production->title }}" class="absolute inset-0 w-full h-full object-cover">
        @endif
        <div class="hero-content text-center z-20 relative">
            <div class="max-w-4xl text-white">
                <h1 class="text-5xl font-bold mb-4">{{ $production->title }}</h1>
                @if($production->subtitle)
                <p class="text-xl mb-6">{{ $production->subtitle }}</p>
                @endif
                
                <div class="flex flex-wrap justify-center gap-6 text-lg">
                    <div class="flex items-center gap-2">
                        <x-unicon name="tabler:calendar" class="size-5"/>
                        <span>{{ $production->start_time->format('l, F j, Y') }}</span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <x-unicon name="tabler:clock" class="size-5"/>
                        <span>{{ $production->start_time->format('g:i A') }}</span>
                        @if($production->doors_time)
                        <span class="opacity-80">(Doors: {{ $production->doors_time->format('g:i A') }})</span>
                        @endif
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <x-unicon name="tabler:map-pin" class="size-5"/>
                        <span>{{ $production->venue_name }}</span>
                    </div>
                </div>

                @if($production->hasTickets() || $production->isFree())
                <div class="mt-8">
                    @if($production->isFree())
                    <div class="badge badge-success badge-lg">FREE EVENT</div>
                    @else
                    <a href="{{ $production->ticket_url }}" target="_blank" class="btn btn-primary btn-lg">
                        <x-unicon name="tabler:ticket" class="size-5"/>
                        Get Tickets - {{ $production->ticket_price_display }}
                    </a>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <!-- Event Description -->
                @if($production->description)
                <div class="card bg-base-100 shadow-lg mb-8">
                    <div class="card-body">
                        <h2 class="card-title text-2xl mb-4">About This Event</h2>
                        <div class="prose max-w-none">
                            {!! nl2br(e($production->description)) !!}
                        </div>
                    </div>
                </div>
                @endif

                <!-- Performers -->
                @if($production->performers && $production->performers->count() > 0)
                <div class="card bg-base-100 shadow-lg mb-8">
                    <div class="card-body">
                        <h2 class="card-title text-2xl mb-6">Featured Artists</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($production->performers as $performer)
                            <div class="flex items-center gap-4 p-4 bg-base-200 rounded-lg">
                                @if($performer->avatar_url)
                                <div class="avatar">
                                    <div class="w-16 h-16 rounded-full">
                                        <img src="{{ $performer->avatar_url }}" alt="{{ $performer->name }}">
                                    </div>
                                </div>
                                @else
                                <div class="avatar placeholder">
                                    <div class="bg-neutral text-neutral-content rounded-full w-16 h-16">
                                        <span class="text-xl">{{ strtoupper(substr($performer->name, 0, 1)) }}</span>
                                    </div>
                                </div>
                                @endif
                                
                                <div class="flex-1">
                                    <h3 class="font-bold text-lg">{{ $performer->name }}</h3>
                                    @if($performer->hometown)
                                    <p class="text-sm opacity-70">{{ $performer->hometown }}</p>
                                    @endif
                                    @if($performer->bio)
                                    <p class="text-sm mt-1">{{ Str::limit($performer->bio, 100) }}</p>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <!-- Venue Information -->
                @if($production->venue_name || $production->venue_address)
                <div class="card bg-base-100 shadow-lg mb-8">
                    <div class="card-body">
                        <h2 class="card-title text-2xl mb-4">Venue Information</h2>
                        <div class="space-y-3">
                            @if($production->venue_name)
                            <div class="flex items-center gap-3">
                                <x-unicon name="tabler:building" class="size-5"/>
                                <span class="text-lg font-semibold">{{ $production->venue_name }}</span>
                            </div>
                            @endif
                            
                            @if($production->venue_address)
                            <div class="flex items-center gap-3">
                                <x-unicon name="tabler:map-pin" class="size-5"/>
                                <span>{{ $production->venue_address }}</span>
                            </div>
                            @endif
                            
                            @if($production->venue_website)
                            <div class="flex items-center gap-3">
                                <x-unicon name="tabler:external-link" class="size-5"/>
                                <a href="{{ $production->venue_website }}" target="_blank" class="link link-primary">
                                    Visit Venue Website
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Event Details Card -->
                <div class="card bg-base-100 shadow-lg mb-8 sticky top-8">
                    <div class="card-body">
                        <h3 class="card-title text-xl mb-4">Event Details</h3>
                        
                        <div class="space-y-4">
                            <div class="flex justify-between items-center py-2 border-b border-base-300">
                                <span class="font-semibold">Date</span>
                                <span>{{ $production->start_time->format('M j, Y') }}</span>
                            </div>
                            
                            <div class="flex justify-between items-center py-2 border-b border-base-300">
                                <span class="font-semibold">Time</span>
                                <span>{{ $production->start_time->format('g:i A') }}</span>
                            </div>
                            
                            @if($production->doors_time)
                            <div class="flex justify-between items-center py-2 border-b border-base-300">
                                <span class="font-semibold">Doors Open</span>
                                <span>{{ $production->doors_time->format('g:i A') }}</span>
                            </div>
                            @endif
                            
                            @if($production->end_time)
                            <div class="flex justify-between items-center py-2 border-b border-base-300">
                                <span class="font-semibold">Ends</span>
                                <span>{{ $production->end_time->format('g:i A') }}</span>
                            </div>
                            @endif
                            
                            <div class="flex justify-between items-center py-2 border-b border-base-300">
                                <span class="font-semibold">Venue</span>
                                <span>{{ $production->venue_name }}</span>
                            </div>
                            
                            @if(!$production->isFree())
                            <div class="flex justify-between items-center py-2 border-b border-base-300">
                                <span class="font-semibold">Tickets</span>
                                <span class="font-bold text-primary">{{ $production->ticket_price_display }}</span>
                            </div>
                            @endif
                            
                            @if($production->age_restriction)
                            <div class="flex justify-between items-center py-2 border-b border-base-300">
                                <span class="font-semibold">Age</span>
                                <span>{{ $production->age_restriction }}</span>
                            </div>
                            @endif
                        </div>

                        @if($production->hasTickets())
                        <div class="mt-6">
                            <a href="{{ $production->ticket_url }}" target="_blank" class="btn btn-primary w-full">
                                <x-unicon name="tabler:ticket" class="size-5"/>
                                Get Tickets
                            </a>
                        </div>
                        @elseif($production->isFree())
                        <div class="mt-6">
                            <div class="alert alert-success">
                                <x-unicon name="tabler:check" class="size-5"/>
                                <span>This is a free event!</span>
                            </div>
                        </div>
                        @endif

                        <!-- Social Sharing -->
                        <div class="mt-6">
                            <h4 class="font-semibold mb-3">Share This Event</h4>
                            <div class="flex gap-2">
                                <a href="https://facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" class="btn btn-square btn-outline btn-sm">
                                    <x-unicon name="tabler:brand-facebook" class="size-4"/>
                                </a>
                                <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($production->title) }}" target="_blank" class="btn btn-square btn-outline btn-sm">
                                    <x-unicon name="tabler:brand-x" class="size-4"/>
                                </a>
                                <button onclick="navigator.share({title: '{{ $production->title }}', url: '{{ request()->url() }}'})" class="btn btn-square btn-outline btn-sm">
                                    <x-unicon name="tabler:share" class="size-4"/>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related Events -->
                @php
                $relatedEvents = \App\Models\Production::publishedUpcoming()
                    ->where('id', '!=', $production->id)
                    ->limit(3)
                    ->get();
                @endphp

                @if($relatedEvents->count() > 0)
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title text-xl mb-4">More Upcoming Events</h3>
                        <div class="space-y-4">
                            @foreach($relatedEvents as $event)
                            <a href="{{ route('events.show', $event) }}" class="block p-3 bg-base-200 rounded-lg hover:bg-base-300 transition-colors">
                                <h4 class="font-semibold mb-1">{{ $event->title }}</h4>
                                <div class="text-sm opacity-70">
                                    {{ $event->start_time->format('M j') }} • {{ $event->venue_name }}
                                </div>
                            </a>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('events.index') }}" class="btn btn-outline btn-sm w-full">
                                View All Events
                            </a>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Back to Events -->
        <div class="mt-16 text-center">
            <a href="{{ route('events.index') }}" class="btn btn-outline">
                <x-unicon name="tabler:arrow-left" class="size-4"/>
                Back to All Events
            </a>
        </div>
    </div>
</x-public.layout>