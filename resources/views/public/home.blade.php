<x-public.layout title="Corvallis Music Collective - Supporting Local Musicians & Community">
    <!-- Hero Section -->
    <div class="hero min-h-screen bg-gradient-to-r from-primary/10 to-secondary/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Building and Connecting Music Communities in Corvallis</h1>
                <p class="py-6 text-lg">
                    We provide shared music resources, affordable practice space, and a supportive
                    community for local musicians to grow, collaborate, and thrive together.
                </p>
                <div class="flex gap-4 justify-center flex-wrap">
                    <a href="/member/register" class="btn btn-primary btn-lg">
                        Join Our Community!
                    </a>
                    <a href="{{ route('practice-space') }}" class="btn btn-outline btn-primary">
                        Book Practice Space
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mission Stats -->
    <div class="bg-base-200 py-16">
        <div class="container mx-auto px-4">
            <div class="stats stats-vertical lg:stats-horizontal shadow w-full">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-title">Active Members</div>
                    <div class="stat-value text-primary">150+</div>
                    <div class="stat-desc">Musicians in our community</div>
                </div>

                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                        </svg>
                    </div>
                    <div class="stat-title">Monthly Events</div>
                    <div class="stat-value text-secondary">12+</div>
                    <div class="stat-desc">Shows and community gatherings</div>
                </div>

                <div class="stat">
                    <div class="stat-figure text-accent">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-title">Practice Hours</div>
                    <div class="stat-value text-accent">500+</div>
                    <div class="stat-desc">Hours booked monthly</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Events -->
    @if($upcomingEvents->count() > 0)
    <div class="py-16">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Upcoming Events</h2>
                <p class="text-lg opacity-70">Join us for these amazing musical experiences</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($upcomingEvents as $event)
                <div class="card bg-base-100 shadow-xl">
                    @if($event->poster_url)
                    <figure>
                        <img src="{{ $event->poster_thumb_url }}" alt="{{ $event->title }}" class="w-full h-48 object-cover">
                    </figure>
                    @endif
                    <div class="card-body">
                        <h2 class="card-title">{{ $event->title }}</h2>
                        <p class="opacity-70">{{ $event->start_time->format('M j, Y g:i A') }}</p>
                        <p class="opacity-70">{{ $event->venue_name }}</p>
                        @if($event->description)
                        <p>{{ Str::limit($event->description, 100) }}</p>
                        @endif
                        <div class="card-actions justify-end">
                            <a href="{{ route('events.show', $event) }}" class="btn btn-primary btn-sm">
                                Learn More
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="text-center mt-8">
                <a href="{{ route('events.index') }}" class="btn btn-outline btn-primary">
                    View All Events
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- What We Do -->
    <div class="bg-base-200 py-16">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">What We Do</h2>
                <p class="text-lg opacity-70">Supporting musicians and building community through various programs</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <div class="text-4xl mb-4"><x-unicon name="tabler:music" class="size-10" /></div>
                        <h3 class="card-title justify-center">Practice Space</h3>
                        <p>Affordable hourly rehearsal space with professional equipment for bands and musicians.</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <div class="text-4xl mb-4"><x-unicon name="tabler:microphone" class="size-10" /></div>
                        <h3 class="card-title justify-center">Live Events</h3>
                        <p>Regular concerts and showcases featuring local and touring musicians.</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <div class="text-4xl mb-4"><x-unicon name="tabler:heart-handshake" class="size-10" /></div>
                        <h3 class="card-title justify-center">Community</h3>
                        <p>Connecting musicians for collaboration, education, and mutual support.</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <div class="text-4xl mb-4"><x-unicon name="tabler:book" class="size-10" /></div>
                        <h3 class="card-title justify-center">Education</h3>
                        <p>Workshops, masterclasses, and mentorship programs for musicians of all levels.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Get Involved -->
    <div class="py-16">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Get Involved</h2>
                <p class="text-lg opacity-70">Join our mission to support the local music community</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="card bg-primary text-primary-content shadow-lg">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center text-2xl">Become a Member</h3>
                        <p>Join our community of musicians and gain access to practice space, events, and networking opportunities.</p>
                        <div class="card-actions justify-center mt-4">
                            <a href="/member/register" class="btn btn-secondary">Join Now</a>
                        </div>
                    </div>
                </div>

                <div class="card bg-secondary text-secondary-content shadow-lg">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center text-2xl">Volunteer</h3>
                        <p>Help us organize events, maintain our space, and support fellow musicians in our community.</p>
                        <div class="card-actions justify-center mt-4">
                            <a href="{{ route('contribute') }}" class="btn btn-primary">Learn More</a>
                        </div>
                    </div>
                </div>

                <div class="card bg-accent text-accent-content shadow-lg">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center text-2xl">Support Us</h3>
                        <p>Your donation helps us provide affordable space and programs for the local music community.</p>
                        <div class="card-actions justify-center mt-4">
                            <a href="{{ route('contribute') }}" class="btn btn-secondary">Contribute</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public.layout>
