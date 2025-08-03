<x-public.layout title="Local Bands Directory | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-accent/10 to-primary/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Local Bands</h1>
                <p class="py-6 text-lg">
                    Discover the amazing bands that call Corvallis Music Collective home
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Search and Filters -->
        <div class="card bg-base-100 shadow-lg mb-8">
            <div class="card-body">
                <h2 class="card-title mb-4">Find Bands</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Search by band name</span>
                        </label>
                        <input type="text" placeholder="Search..." class="input input-bordered" />
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
                            <option>Indie</option>
                            <option>Metal</option>
                            <option>Blues</option>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Location</span>
                        </label>
                        <select class="select select-bordered">
                            <option>All Locations</option>
                            <option>Corvallis</option>
                            <option>Albany</option>
                            <option>Eugene</option>
                            <option>Portland</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-between items-center mt-4">
                    <button class="btn btn-primary">Search</button>
                    <span class="text-sm opacity-70">{{ $bands->total() }} bands found</span>
                </div>
            </div>
        </div>

        <!-- Bands Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            @forelse($bands as $band)
            <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow">
                @if($band->avatar_url)
                <figure>
                    <img src="{{ $band->avatar_thumb_url }}" alt="{{ $band->name }}" class="w-full h-48 object-cover">
                </figure>
                @else
                <figure class="bg-gradient-to-r from-primary/20 to-secondary/20 h-48 flex items-center justify-center">
                    <div class="text-6xl opacity-50"><x-unicon name="tabler:guitar-pick" class="size-16" /></div>
                </figure>
                @endif
                
                <div class="card-body">
                    <h2 class="card-title">
                        {{ $band->name }}
                        @if($band->activeMembers->count() > 0)
                        <div class="badge badge-primary">{{ $band->activeMembers->count() }} members</div>
                        @endif
                    </h2>
                    
                    @if($band->hometown)
                    <div class="flex items-center gap-2 text-sm opacity-70">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>{{ $band->hometown }}</span>
                    </div>
                    @endif
                    
                    @if($band->genres->count() > 0)
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($band->genres->take(3) as $genre)
                        <span class="badge badge-secondary badge-sm">{{ $genre->name }}</span>
                        @endforeach
                        @if($band->genres->count() > 3)
                        <span class="badge badge-outline badge-sm">+{{ $band->genres->count() - 3 }}</span>
                        @endif
                    </div>
                    @endif
                    
                    @if($band->bio)
                    <p class="text-sm mt-3">{{ Str::limit($band->bio, 120) }}</p>
                    @endif
                    
                    @if($band->activeMembers->count() > 0)
                    <div class="mt-3">
                        <div class="text-xs font-semibold mb-2">Members:</div>
                        <div class="flex -space-x-2">
                            @foreach($band->activeMembers->take(4) as $member)
                            <div class="avatar">
                                <div class="w-8 rounded-full border-2 border-base-100">
                                    <img src="{{ $member->profile->avatar_thumb_url ?? 'https://via.placeholder.com/32' }}" 
                                         alt="{{ $member->name }}" 
                                         title="{{ $member->name }}" />
                                </div>
                            </div>
                            @endforeach
                            @if($band->activeMembers->count() > 4)
                            <div class="avatar placeholder">
                                <div class="w-8 rounded-full bg-neutral text-neutral-content border-2 border-base-100">
                                    <span class="text-xs">+{{ $band->activeMembers->count() - 4 }}</span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    <!-- Social Links -->
                    @if($band->links && count($band->links) > 0)
                    <div class="flex gap-2 mt-3">
                        @foreach(array_slice($band->links, 0, 4) as $link)
                        <a href="{{ $link['url'] }}" target="_blank" class="btn btn-circle btn-sm btn-outline" title="{{ $link['name'] }}">
                            @if(str_contains(strtolower($link['name']), 'spotify'))
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.84-.179-.84-.66 0-.359.24-.66.54-.78 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.78.24 1.021zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.42 1.56-.299.421-1.02.599-1.559.3z"/>
                            </svg>
                            @elseif(str_contains(strtolower($link['name']), 'instagram'))
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                            @elseif(str_contains(strtolower($link['name']), 'facebook'))
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            @endif
                        </a>
                        @endforeach
                    </div>
                    @endif
                    
                    <div class="card-actions justify-end mt-4">
                        <a href="{{ route('bands.show', $band) }}" class="btn btn-primary btn-sm">
                            View Band
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full text-center py-16">
                <div class="text-6xl mb-4"><x-unicon name="tabler:guitar-pick" class="size-16" /></div>
                <h3 class="text-2xl font-bold mb-4">No bands found</h3>
                <p class="text-lg opacity-70">Try adjusting your search criteria or check back later as more bands join our community.</p>
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($bands->hasPages())
        <div class="flex justify-center">
            <div class="join">
                @if($bands->onFirstPage())
                <button class="join-item btn btn-disabled">«</button>
                @else
                <a href="{{ $bands->previousPageUrl() }}" class="join-item btn">«</a>
                @endif
                
                @for($i = 1; $i <= $bands->lastPage(); $i++)
                    @if($i == $bands->currentPage())
                    <button class="join-item btn btn-active">{{ $i }}</button>
                    @else
                    <a href="{{ $bands->url($i) }}" class="join-item btn">{{ $i }}</a>
                    @endif
                @endfor
                
                @if($bands->hasMorePages())
                <a href="{{ $bands->nextPageUrl() }}" class="join-item btn">»</a>
                @else
                <button class="join-item btn btn-disabled">»</button>
                @endif
            </div>
        </div>
        @endif

        <!-- Call to Action -->
        <div class="text-center mt-16 bg-base-200 rounded-lg p-8">
            <h2 class="text-3xl font-bold mb-4">Start a Band at CMC</h2>
            <p class="text-lg mb-6">
                Looking for bandmates or want to showcase your existing band? Join our community and connect with other musicians.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/member/register" class="btn btn-primary">Join as a Member</a>
                <a href="{{ route('members.index') }}" class="btn btn-outline btn-secondary">Find Musicians</a>
                <a href="{{ route('contact') }}" class="btn btn-outline btn-accent">Contact Us</a>
            </div>
        </div>
    </div>
</x-public.layout>