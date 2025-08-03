<x-public.layout title="Events - Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-secondary/10 to-accent/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Upcoming Events</h1>
                <p class="py-6 text-lg">
                    Discover amazing live music and community events happening at CMC
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Search and Filters -->
        <div class="card bg-base-100 shadow-lg mb-8">
            <div class="card-body">
                <h2 class="card-title mb-4">Find Events</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Search events</span>
                        </label>
                        <input type="text" placeholder="Search..." class="input input-bordered" />
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Date</span>
                        </label>
                        <select class="select select-bordered">
                            <option>All Dates</option>
                            <option>This Week</option>
                            <option>This Month</option>
                            <option>Next Month</option>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Genre</span>
                        </label>
                        <select class="select select-bordered">
                            <option>All Genres</option>
                            <option>Rock</option>
                            <option>Jazz</option>
                            <option>Folk</option>
                            <option>Electronic</option>
                            <option>Classical</option>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Venue</span>
                        </label>
                        <select class="select select-bordered">
                            <option>All Venues</option>
                            <option>CMC Main Room</option>
                            <option>External Venues</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-between items-center mt-4">
                    <button class="btn btn-primary">Search</button>
                    <span class="text-sm opacity-70">{{ $events->total() }} events found</span>
                </div>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            @forelse($events as $event)
            <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow">
                @if($event->poster_url)
                <figure>
                    <img src="{{ $event->poster_thumb_url }}" alt="{{ $event->title }}" class="w-full h-48 object-cover">
                </figure>
                @else
                <figure class="bg-gradient-to-r from-primary/20 to-secondary/20 h-48 flex items-center justify-center">
                    <div class="text-6xl opacity-50">🎵</div>
                </figure>
                @endif
                
                <div class="card-body">
                    <h2 class="card-title">
                        {{ $event->title }}
                        @if($event->isFree())
                        <div class="badge badge-success">FREE</div>
                        @endif
                    </h2>
                    
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>{{ $event->start_time->format('M j, Y') }}</span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>{{ $event->start_time->format('g:i A') }}</span>
                            @if($event->doors_time)
                            <span class="opacity-70">(Doors: {{ $event->doors_time->format('g:i A') }})</span>
                            @endif
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>{{ $event->venue_name }}</span>
                        </div>
                        
                        @if(!$event->isFree())
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                            <span>{{ $event->ticket_price_display }}</span>
                        </div>
                        @endif
                    </div>
                    
                    @if($event->description)
                    <p class="text-sm opacity-70 mt-2">{{ Str::limit($event->description, 100) }}</p>
                    @endif
                    
                    @if($event->performers->count() > 0)
                    <div class="mt-3">
                        <div class="text-xs font-semibold mb-1">Featuring:</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($event->performers->take(3) as $performer)
                            <span class="badge badge-outline badge-sm">{{ $performer->name }}</span>
                            @endforeach
                            @if($event->performers->count() > 3)
                            <span class="badge badge-outline badge-sm">+{{ $event->performers->count() - 3 }} more</span>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    <div class="card-actions justify-between items-center mt-4">
                        <div>
                            @if($event->hasTickets())
                            <a href="{{ $event->ticket_url }}" target="_blank" class="btn btn-primary btn-sm">
                                Get Tickets
                            </a>
                            @endif
                        </div>
                        <a href="{{ route('events.show', $event) }}" class="btn btn-outline btn-sm">
                            Details
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full text-center py-16">
                <div class="text-6xl mb-4">🎭</div>
                <h3 class="text-2xl font-bold mb-4">No events found</h3>
                <p class="text-lg opacity-70">Check back soon for upcoming shows and community events!</p>
                <a href="{{ route('contact') }}" class="btn btn-primary mt-4">Contact Us About Performing</a>
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($events->hasPages())
        <div class="flex justify-center">
            <div class="join">
                @if($events->onFirstPage())
                <button class="join-item btn btn-disabled">«</button>
                @else
                <a href="{{ $events->previousPageUrl() }}" class="join-item btn">«</a>
                @endif
                
                @for($i = 1; $i <= $events->lastPage(); $i++)
                    @if($i == $events->currentPage())
                    <button class="join-item btn btn-active">{{ $i }}</button>
                    @else
                    <a href="{{ $events->url($i) }}" class="join-item btn">{{ $i }}</a>
                    @endif
                @endfor
                
                @if($events->hasMorePages())
                <a href="{{ $events->nextPageUrl() }}" class="join-item btn">»</a>
                @else
                <button class="join-item btn btn-disabled">»</button>
                @endif
            </div>
        </div>
        @endif

        <!-- Call to Action -->
        <div class="text-center mt-16 bg-base-200 rounded-lg p-8">
            <h2 class="text-3xl font-bold mb-4">Want to Perform at CMC?</h2>
            <p class="text-lg mb-6">
                We're always looking for talented local musicians to showcase at our events.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('contact') }}" class="btn btn-primary">Submit Performance Inquiry</a>
                <a href="/member/register" class="btn btn-outline btn-secondary">Become a Member</a>
            </div>
        </div>
    </div>
</x-public.layout>