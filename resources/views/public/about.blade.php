<x-public.layout title="About CMC - Supporting Corvallis Musicians | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-accent/20">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">About CMC</h1>
                <p class="py-6 text-lg">
                    Building and connecting music communities around Corvallis since 2024
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Mission Statement -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-20">
            <div>
                <h2 class="text-4xl font-bold mb-6">Our Mission</h2>
                <p class="text-lg mb-4">
                    The Corvallis Music Collective exists to foster a vibrant, inclusive music community
                    that supports musicians at every stage of their journey.
                </p>
                <p class="text-lg mb-4">
                    We provide affordable practice space, performance opportunities, educational programs,
                    and connections that help local musicians thrive and grow together.
                </p>
                <p class="text-lg">
                    Through our programs and community events, we're building bridges between musicians,
                    genres, and generations to create something bigger than any of us could achieve alone.
                </p>
            </div>
            <div class="card bg-primary text-primary-content">
                <div class="card-body">
                    <h3 class="card-title text-2xl">Our Values</h3>
                    <ul class="space-y-2 list-disc list-inside">
                        <li><strong>Inclusivity:</strong> Welcoming musicians of all backgrounds and skill levels</li>
                        <li><strong>Accessibility:</strong> Keeping music affordable and available to all</li>
                        <li><strong>Community:</strong> Building lasting connections and collaborations</li>
                        <li><strong>Growth:</strong> Supporting artistic and personal development</li>
                        <li><strong>Sustainability:</strong> Creating a lasting resource for our community</li>
                    </ul>
                    <div class="card-actions justify-end mt-4">
                        <a href="{{ route('bylaws') }}" class="btn btn-outline">
                            <x-tabler-file-text class="size-5" />
                            Read Our Bylaws
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="mb-20">
            <h2 class="text-4xl font-bold text-center mb-12">Our Story</h2>
            <ul class="timeline timeline-vertical">
                <li>
                    <div class="timeline-start timeline-box">
                        <h3 class="font-bold">January 2024</h3>
                        <p>Founded by local musicians frustrated with the lack of affordable practice space in
                            Corvallis.</p>
                    </div>
                    <div class="timeline-middle">
                        <x-tabler-circle-check-filled class="text-primary size-5" />
                    </div>
                    <hr class="bg-primary" />
                </li>

                <li>
                    <hr class="bg-primary" />
                    <div class="timeline-middle">
                        <x-tabler-circle-check-filled class="text-secondary size-5" />
                    </div>
                    <div class="timeline-end timeline-box">
                        <h3 class="font-bold">April 2024</h3>
                        <p>Kickoff fundraiser at the Whiteside</p>
                    </div>
                    <hr class="bg-secondary" />
                </li>

                <li>
                    <hr class="bg-secondary" />
                    <div class="timeline-start timeline-box">
                        <h3 class="font-bold">June 2024</h3>
                        <p>501(c)(3) status approved!</p>
                    </div>
                    <div class="timeline-middle">
                        <x-tabler-circle-check-filled class="text-accent size-5" />

                    </div>
                    <hr class="bg-accent" />
                </li>

                <li>
                    <hr class="bg-accent" />
                    <div class="timeline-middle">
                        <x-tabler-circle-check-filled class="text-warning size-5" />

                    </div>
                    <div class="timeline-end timeline-box">
                        <h3 class="font-bold">August 2024</h3>
                        <p>Located and leased our new practice space – ready for a lick of paint.</p>
                    </div>
                    <hr class="bg-warning" />
                </li>

                <li>
                    <hr class="bg-warning" />
                    <div class="timeline-start timeline-box">
                        <h3 class="font-bold">January 2025</h3>
                        <p>Kicked off the New Year with our first show! We've since run more than 25 concerts out of our
                            space.</p>
                    </div>
                    <div class="timeline-middle">
                        <x-tabler-circle-check-filled class="text-success size-5" />

                    </div>
                </li>
            </ul>
        </div>

        <!-- Board & Staff -->
        <div class="mb-20">
            <h2 class="text-4xl font-bold text-center mb-12">Leadership</h2>

            <div class="mb-12">
                <h3 class="text-2xl font-bold mb-6">Board of Directors</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @forelse($boardMembers as $member)
                        <div class="card bg-base-100 shadow-lg">
                            <div class="card-body text-center">
                                <div class="avatar">
                                    <div class="w-24 rounded-full mx-auto mb-4">
                                        <img src="{{ $member->profile_image_url ?: 'https://via.placeholder.com/150' }}"
                                            alt="{{ $member->name }}" />
                                    </div>
                                </div>
                                <h4 class="card-title justify-center">
                                    @if ($member->user && $member->user->profile && $member->user->profile->isVisible())
                                        <a href="{{ route('members.show', $member->user->profile) }}"
                                            class="link link-hover">
                                            {{ $member->name }}
                                        </a>
                                    @else
                                        {{ $member->name }}
                                    @endif
                                </h4>
                                @if ($member->title)
                                    <p class="text-sm opacity-70">{{ $member->title }}</p>
                                @endif
                                @if ($member->bio)
                                    <p>{{ $member->bio }}</p>
                                @endif

                                @if ($member->social_links)
                                    <div class="flex justify-center gap-2 mt-3">
                                        @foreach ($member->social_links as $link)
                                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                                                class="btn btn-sm btn-ghost btn-circle"
                                                title="{{ ucfirst($link['platform']) }}">
                                                @switch($link['platform'])
                                                    @case('website')
                                                        <x-icon name="tabler-world" class="w-4 h-4" />
                                                    @break

                                                    @case('linkedin')
                                                        <x-icon name="tabler-brand-linkedin" class="w-4 h-4" />
                                                    @break

                                                    @case('twitter')
                                                        <x-icon name="tabler-brand-x" class="w-4 h-4" />
                                                    @break

                                                    @case('facebook')
                                                        <x-icon name="tabler-brand-facebook" class="w-4 h-4" />
                                                    @break

                                                    @case('instagram')
                                                        <x-icon name="tabler-brand-instagram" class="w-4 h-4" />
                                                    @break

                                                    @case('github')
                                                        <x-icon name="tabler-brand-github" class="w-4 h-4" />
                                                    @break
                                                @endswitch
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        @empty
                            <div class="col-span-full text-center py-8">
                                <p class="text-lg opacity-70">Board member information will be available soon.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                @if ($staffMembers->isNotEmpty())
                    <div>
                        <h3 class="text-2xl font-bold mb-6">Staff</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @forelse($staffMembers as $member)
                                <div class="card bg-base-100 shadow-lg">
                                    <div class="card-body">
                                        <div class="flex items-center gap-4">
                                            <div class="avatar">
                                                <div class="w-16 rounded-full">
                                                    <img src="{{ $member->profile_image_url ?: 'https://via.placeholder.com/150' }}"
                                                        alt="{{ $member->name }}" />
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="card-title">
                                                    @if ($member->user && $member->user->profile && $member->user->profile->isVisible())
                                                        <a href="{{ route('members.show', $member->user->profile) }}"
                                                            class="link link-hover">
                                                            {{ $member->name }}
                                                        </a>
                                                    @else
                                                        {{ $member->name }}
                                                    @endif
                                                </h4>
                                                @if ($member->title)
                                                    <p class="text-sm opacity-70">{{ $member->title }}</p>
                                                @endif
                                                @if ($member->bio)
                                                    <p class="mt-1">{{ $member->bio }}</p>
                                                @endif

                                                @if ($member->social_links)
                                                    <div class="flex gap-1 mt-2">
                                                        @foreach ($member->social_links as $link)
                                                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                                                                class="btn btn-xs btn-ghost btn-circle"
                                                                title="{{ ucfirst($link['platform']) }}">
                                                                @switch($link['platform'])
                                                                    @case('website')
                                                                        <x-icon name="tabler-world" class="w-3 h-3" />
                                                                    @break

                                                                    @case('linkedin')
                                                                        <x-icon name="tabler-brand-linkedin" class="w-3 h-3" />
                                                                    @break

                                                                    @case('twitter')
                                                                        <x-icon name="tabler-brand-x" class="w-3 h-3" />
                                                                    @break

                                                                    @case('facebook')
                                                                        <x-icon name="tabler-brand-facebook" class="w-3 h-3" />
                                                                    @break

                                                                    @case('instagram')
                                                                        <x-icon name="tabler-brand-instagram" class="w-3 h-3" />
                                                                    @break

                                                                    @case('github')
                                                                        <x-icon name="tabler-brand-github" class="w-3 h-3" />
                                                                    @break
                                                                @endswitch
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @empty
                                    <div class="col-span-full text-center py-8">
                                        <p class="text-lg opacity-70">Staff information will be available soon.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                </div>
                @endif
                <!-- Call to Action -->
                <div class="text-center bg-base-200 rounded-lg p-12">
                    <h2 class="text-3xl font-bold mb-4">Join Our Community</h2>
                    <p class="text-lg mb-6">
                        Ready to be part of something special? Join the Corvallis Music Collective today.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="/member/register" class="btn btn-primary">Become a Member</a>
                        <a href="{{ route('contribute') }}" class="btn btn-outline btn-secondary">Contribute</a>
                    </div>
                </div>
            </div>
        </x-public.layout>
