<x-public.layout title="Corvallis Music Collective - Supporting Local Musicians & Community">
    <!-- Hero Section -->
    <div class="hero min-h-screen bg-secondary/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Building and Connecting Music Communities in Corvallis</h1>
                <p class="py-6 text-lg">
                    We provide shared music resources, affordable practice space, and a supportive
                    community for local musicians to grow, collaborate, and thrive together.
                </p>
                <div class="flex flex-col gap-4 justify-center flex-wrap items-center">
                    <a href="/member/register" class="btn btn-primary btn-lg btn-wide">
                        Join Our Community!
                    </a>
                    <a href="{{ route('programs') }}" class="btn btn-outline btn-primary btn-wide">
                        Explore Programs
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Mission Stats - Hidden for now --}}
    {{-- <div class="bg-base-200 py-16">
        <div class="container mx-auto px-4">
            <div class="stats stats-vertical lg:stats-horizontal shadow w-full">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <x-unicon name="tabler:users" class="size-8"/>
                    </div>
                    <div class="stat-title">Active Members</div>
                    <div class="stat-value text-primary">{{ number_format($stats['active_members']) }}{{ $stats['active_members'] > 0 ? '+' : '' }}</div>
                    <div class="stat-desc">Musicians in our community</div>
                </div>

                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <x-unicon name="tabler:music" class="size-8"/>
                    </div>
                    <div class="stat-title">This Month's Events</div>
                    <div class="stat-value text-secondary">{{ $stats['monthly_events'] }}</div>
                    <div class="stat-desc">Shows and community gatherings</div>
                </div>

                <div class="stat">
                    <div class="stat-figure text-accent">
                        <x-unicon name="tabler:clock" class="size-8"/>
                    </div>
                    <div class="stat-title">Practice Hours</div>
                    <div class="stat-value text-accent">{{ number_format($stats['practice_hours']) }}{{ $stats['practice_hours'] > 0 ? '+' : '' }}</div>
                    <div class="stat-desc">Hours booked this month</div>
                </div>
            </div>
        </div>
    </div> --}}

    <!-- Upcoming Events -->
    @if ($upcomingEvents->count() > 0)
        <div class="py-16">
            <div class="container mx-auto px-4">
                <div class="text-center mb-12">
                    <h2 class="text-4xl font-bold mb-4">Upcoming Events</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mx-auto items-center justify-center">
                    @foreach ($upcomingEvents as $event)
                        <x-event-card :item="$event" />
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
        <div class="container mx-auto px-4 flex flex-col items-center">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">What We Do</h2>
                <p class="text-lg opacity-70">Supporting musicians and building community through various programs</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <x-tabler-music class="size-10 mx-auto" />
                        <h3 class="card-title justify-center">Practice Space</h3>
                        <p>Affordable hourly rehearsal space with professional equipment for bands and musicians.</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <x-tabler-microphone class="size-10 mx-auto" />
                        <h3 class="card-title justify-center">Live Events</h3>
                        <p>Regular concerts and showcases featuring local and touring musicians.</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <x-tabler-heart-handshake class="size-10 mx-auto" />
                        <h3 class="card-title justify-center">Community</h3>
                        <p>Connecting musicians for collaboration, education, and mutual support.</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <x-tabler-book class="size-10 mx-auto" />
                        <h3 class="card-title justify-center">Education</h3>
                        <p>Workshops, masterclasses, and mentorship programs for musicians of all levels.</p>
                    </div>
                </div>
            </div>
            <a href="{{ route('programs') }}" class=" btn btn-wide btn-outline btn-primary mt-8">View All Programs</a>
        </div>
    </div>

    <!-- Major Sponsors -->
    @if ($majorSponsors->count() > 0)
        <div class="bg-base-200 py-16">
            <div class="container mx-auto px-4">
                <div class="text-center mb-12">
                    <h2 class="text-4xl font-bold mb-4">Our Sponsors</h2>
                    <p class="text-lg opacity-70">Thank you to our community partners!</p>
                </div>

                <div class="flex flex-wrap w-full gap-3 justify-center">
                    @foreach ($majorSponsors as $sponsor)
                        @php
                            $logo = $sponsor->getFirstMediaUrl('logo');
                        @endphp
                        @if ($sponsor->website_url)
                            <a href="{{ $sponsor->website_url }}" target="_blank" rel="noopener noreferrer"
                                class="transition-transform hover:scale-105 flex flex-col items-center justify-center p-2 bg-base-100 w-48 ">
                                @if ($logo)
                                    <img src="{{ $logo }}" alt="{{ $sponsor->name }}"
                                        class="w-full max-h-full object-contain">
                                @endif
                                <div class="text-center grow flex justify-center items-center">
                                    <span class="text-wrap text-sm">{{ $sponsor->name }}</span>
                                </div>
                            </a>
                        @else
                            <div class="flex items-center justify-center w-full h-32">
                                @if ($logo)
                                    <img src="{{ $logo }}" alt="{{ $sponsor->name }}"
                                        class="max-w-full max-h-full object-contain">
                                @else
                                    <div class="text-center p-4 border-2 border-dashed rounded-lg">
                                        <span class="font-semibold">{{ $sponsor->name }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="text-center mt-8">
                    <a href="{{ route('sponsors') }}" class="btn btn-outline btn-primary">
                        View All Sponsors
                    </a>
                </div>
            </div>
        </div>
    @endif

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
                        <p>Join our community of musicians and gain access to practice space, events, and networking
                            opportunities.</p>
                        <div class="card-actions justify-center mt-4">
                            <a href="/member/register" class="btn btn-secondary">Join Now</a>
                        </div>
                    </div>
                </div>

                <div class="card bg-secondary text-secondary-content shadow-lg">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center text-2xl">Volunteer</h3>
                        <p>Help us organize events, maintain our space, and support fellow musicians in our community.
                        </p>
                        <div class="card-actions justify-center mt-4">
                            <a href="{{ route('contribute') }}" class="btn btn-primary">Learn More</a>
                        </div>
                    </div>
                </div>

                <div class="card bg-accent text-accent-content shadow-lg">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center text-2xl">Support Us</h3>
                        <p>Your donation helps us provide affordable space and programs for the local music community.
                        </p>
                        <div class="card-actions justify-center mt-4">
                            <a href="{{ route('contribute') }}" class="btn btn-secondary">Contribute</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public.layout>
