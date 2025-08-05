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
                            @foreach (\Spatie\Tags\Tag::getWithType('genre') as $genre)
                                <option value="{{ $genre->id }}">{{ $genre->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Location</span>
                        </label>
                        <select class="select select-bordered">
                            <option>All Locations</option>
                            @foreach (\App\Models\BandProfile::distinct('hometown')->pluck('hometown') as $location)
                                <option value="{{ $location }}">{{ $location }}</option>
                            @endforeach
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
                    <figure
                        class="bg-gradient-to-r from-primary/20 to-secondary/20 h-48 flex items-center justify-center">
                        @if ($band->avatar_url)
                            <img src="{{ $band->avatar_thumb_url }}" alt="{{ $band->name }}"
                                class="w-full h-48 object-cover">
                        @else
                            <div class="text-6xl opacity-50"><x-unicon name="tabler:guitar-pick" class="size-16" />
                            </div>
                        @endif
                    </figure>

                    <div class="card-body">
                        <h2 class="card-title">
                            {{ $band->name }}
                        </h2>

                        @if ($band->hometown)
                            <div class="flex items-center gap-2 text-sm opacity-70">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>{{ $band->hometown }}</span>
                            </div>
                        @endif

                        @if ($band->genres->count() > 0)
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach ($band->genres->take(3) as $genre)
                                    <span class="badge badge-secondary badge-sm">{{ $genre->name }}</span>
                                @endforeach
                                @if ($band->genres->count() > 3)
                                    <span class="badge badge-outline badge-sm">+{{ $band->genres->count() - 3 }}</span>
                                @endif
                            </div>
                        @endif

                        @if ($band->bio)
                            <p class="text-sm mt-3">{{ Str::limit($band->bio, 120) }}</p>
                        @endif
                        {{--
                        @if ($band->activeMembers->count() > 0)
                            <div class="mt-3">
                                <div class="text-xs font-semibold mb-2">Members:</div>
                                <div class="flex -space-x-2">
                                    @foreach ($band->activeMembers->take(4) as $member)
                                        <div class="avatar">
                                            <div class="w-8 rounded-full border-2 border-base-100">
                                                <img src="{{ $member->profile->avatar_thumb_url ?? 'https://via.placeholder.com/32' }}"
                                                    alt="{{ $member->name }}" title="{{ $member->name }}" />
                                            </div>
                                        </div>
                                    @endforeach
                                    @if ($band->activeMembers->count() > 4)
                                        <div class="avatar placeholder">
                                            <div
                                                class="w-8 rounded-full bg-neutral text-neutral-content border-2 border-base-100">
                                                <span class="text-xs">+{{ $band->activeMembers->count() - 4 }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif --}}



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
                    <p class="text-lg opacity-70">Try adjusting your search criteria or check back later as more bands
                        join our community.</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if ($bands->hasPages())
            <div class="flex justify-center">
                <div class="join">
                    @if ($bands->onFirstPage())
                        <button class="join-item btn btn-disabled">«</button>
                    @else
                        <a href="{{ $bands->previousPageUrl() }}" class="join-item btn">«</a>
                    @endif

                    @for ($i = 1; $i <= $bands->lastPage(); $i++)
                        @if ($i == $bands->currentPage())
                            <button class="join-item btn btn-active">{{ $i }}</button>
                        @else
                            <a href="{{ $bands->url($i) }}" class="join-item btn">{{ $i }}</a>
                        @endif
                    @endfor

                    @if ($bands->hasMorePages())
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
                Looking for bandmates or want to showcase your existing band? Join our community and connect with other
                musicians.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/member/register" class="btn btn-primary">Join as a Member</a>
                <a href="{{ route('members.index') }}" class="btn btn-outline btn-secondary">Find Musicians</a>
                <a href="{{ route('contact') }}" class="btn btn-outline btn-accent">Contact Us</a>
            </div>
        </div>
    </div>
</x-public.layout>
