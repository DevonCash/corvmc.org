<x-public.layout title="Musician Members Directory | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-primary/10 to-secondary/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Our Musicians</h1>
                <p class="py-6 text-lg">
                    Meet the talented artists who make up our vibrant music community
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Search and Filters -->
        <div class="card bg-base-100 shadow-lg mb-8">
            <div class="card-body">
                <h2 class="card-title mb-4">Find Musicians</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Search by name or instrument</span>
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
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Instrument</span>
                        </label>
                        <select class="select select-bordered">
                            <option>All Instruments</option>
                            <option>Guitar</option>
                            <option>Bass</option>
                            <option>Drums</option>
                            <option>Vocals</option>
                            <option>Piano</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-between items-center mt-4">
                    <button class="btn btn-primary">Search</button>
                    <span class="text-sm opacity-70">{{ $members->total() }} musicians found</span>
                </div>
            </div>
        </div>

        <!-- Members Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
            @forelse($members as $member)
            <div class="card bg-base-100 shadow-lg hover:shadow-xl transition-shadow">
                <figure class="px-6 pt-6">
                    <div class="avatar">
                        <div class="w-24 rounded-full">
                            <img src="{{ $member->avatar_thumb_url }}" alt="{{ $member->user->name }}" />
                        </div>
                    </div>
                </figure>
                
                <div class="card-body items-center text-center">
                    <h2 class="card-title">{{ $member->user->name }}</h2>
                    
                    @if($member->hometown)
                    <p class="text-sm opacity-70"><x-unicon name="tabler:map-pin" class="size-4 inline mr-1" /> {{ $member->hometown }}</p>
                    @endif
                    
                    @if($member->skills)
                    <div class="flex flex-wrap gap-1 justify-center mt-2">
                        @foreach(array_slice($member->skills, 0, 3) as $skill)
                        <span class="badge badge-primary badge-sm">{{ $skill }}</span>
                        @endforeach
                        @if(count($member->skills) > 3)
                        <span class="badge badge-outline badge-sm">+{{ count($member->skills) - 3 }}</span>
                        @endif
                    </div>
                    @endif
                    
                    @if($member->bio)
                    <p class="text-sm mt-2">{{ Str::limit($member->bio, 80) }}</p>
                    @endif
                    
                    <div class="card-actions justify-end mt-4">
                        <a href="{{ route('members.show', $member) }}" class="btn btn-primary btn-sm">
                            View Profile
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full text-center py-16">
                <div class="text-6xl mb-4"><x-unicon name="tabler:music" class="size-16" /></div>
                <h3 class="text-2xl font-bold mb-4">No musicians found</h3>
                <p class="text-lg opacity-70">Try adjusting your search criteria or check back later.</p>
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($members->hasPages())
        <div class="flex justify-center">
            <div class="join">
                @if($members->onFirstPage())
                <button class="join-item btn btn-disabled">«</button>
                @else
                <a href="{{ $members->previousPageUrl() }}" class="join-item btn">«</a>
                @endif
                
                @for($i = 1; $i <= $members->lastPage(); $i++)
                    @if($i == $members->currentPage())
                    <button class="join-item btn btn-active">{{ $i }}</button>
                    @else
                    <a href="{{ $members->url($i) }}" class="join-item btn">{{ $i }}</a>
                    @endif
                @endfor
                
                @if($members->hasMorePages())
                <a href="{{ $members->nextPageUrl() }}" class="join-item btn">»</a>
                @else
                <button class="join-item btn btn-disabled">»</button>
                @endif
            </div>
        </div>
        @endif

        <!-- Call to Action -->
        <div class="text-center mt-16 bg-base-200 rounded-lg p-8">
            <h2 class="text-3xl font-bold mb-4">Join Our Community</h2>
            <p class="text-lg mb-6">
                Are you a musician in the Corvallis area? Join our directory and connect with other local artists.
            </p>
            <a href="/member/register" class="btn btn-primary btn-lg">Become a Member</a>
        </div>
    </div>
</x-public.layout>