<x-public.layout title="Programs | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Programs</h1>
                <p class="py-6 text-lg">
                    Practice spaces, performances, meetups & clubs for the music community
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Practice Space Section -->
        <section class="mb-20 bg-success/10 p-8 lg:p-12">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Practice Space</h2>
                <p class="text-lg max-w-3xl mx-auto">
                    Professional rehearsal rooms available to CMC members, equipped with the gear you need to develop
                    your craft.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-12">
                <div>
                    <h3 class="text-2xl font-bold mb-4">Affordable Practice Space for Musicians</h3>
                    <p class="text-lg mb-4">
                        Our practice rooms are equipped with professional gear and designed for musicians
                        who need a reliable space to rehearse, record demos, and develop their craft.
                    </p>
                    <p class="text-lg mb-4">
                        Members can book hourly sessions in our sound treated practice room, complete with
                        a PA system, microphones, and all the essentials for a productive practice session.
                    </p>
                    <div class="alert alert-info">
                        <x-tabler-user-circle class="size-6" />
                        <span><strong>Members Only</strong><br /> Practice space access requires a free CMC membership
                        </span>
                    </div>
                </div>

                <div class="card bg-success text-success-content shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-center mb-4">
                            <x-tabler-music class="size-24 opacity-30" />
                        </div>
                        <h4 class="card-title justify-center">Room Features</h4>
                        <ul class="space-y-2 list-disc list-inside">
                            <li>Sound treated walls</li>
                            <li>Full PA system</li>
                            <li>Microphones & stands</li>
                            <li>Drum kit (cymbals & hardware)</li>
                            <li>Guitar & bass amplifiers</li>
                            <li>Comfortable seating</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Pricing Summary -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <h4 class="card-title justify-center">Standard Rate</h4>
                        <div class="text-2xl font-bold text-primary">$15/hour</div>
                        <p class="text-sm">All equipment included</p>
                    </div>
                </div>
                <div class="card bg-primary text-primary-content shadow-lg">
                    <div class="card-body text-center">
                        <h4 class="card-title justify-center">Sustaining Members</h4>
                        <div class="text-2xl font-bold">up to 10 Free Hours</div>
                        <p class="text-sm">then $15/hour</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Shows/Performances Section -->
        <section class="mb-20 bg-primary/10 p-8 lg:p-12">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Shows & Performances</h2>
                <p class="text-lg max-w-3xl mx-auto">
                    Showcase your talent and connect with the community through our regular performance opportunities
                    and special events.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-12">
                <div class="card bg-primary text-primary-content shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-center mb-4">
                            <x-tabler-microphone-2 class="size-24 opacity-30" />
                        </div>
                        <h4 class="card-title justify-center">Performance Opportunities</h4>
                        <ul class="space-y-2 list-disc list-inside">
                            <li>Monthly showcase events</li>
                            <li>Open mic nights</li>
                            <li>Collaborative performances</li>
                            <li>Community festivals</li>
                            <li>Recording showcases</li>
                        </ul>
                    </div>
                </div>

                <div>
                    <h3 class="text-2xl font-bold mb-4">Perform & Connect</h3>
                    <p class="text-lg mb-4">
                        Whether you're a seasoned performer or just starting out, our performance programs
                        provide supportive environments to share your music with appreciative audiences.
                    </p>
                    <p class="text-lg mb-6">
                        From intimate acoustic sets to full band productions, we create spaces where
                        musicians can grow, collaborate, and celebrate the power of live music.
                    </p>

                    {{-- <div class="grid grid-cols-2 gap-4">
                        <div class="card bg-base-200">
                            <div class="card-body p-4 text-center">
                                <h5 class="font-bold">Monthly Shows</h5>
                                <p class="text-sm">Regular performance slots</p>
                            </div>
                        </div>
                        <div class="card bg-base-200">
                            <div class="card-body p-4 text-center">
                                <h5 class="font-bold">Open Mics</h5>
                                <p class="text-sm">Welcoming stage time</p>
                            </div>
                        </div>
                    </div> --}}
                    <div class="text-center flex gap-4 justify-center">
                        <a href="{{ route('events.index') }}" class="btn btn-primary btn-lg">View Upcoming Shows</a>
                        <a href="{{ route('contact') }}?topic=performance"
                            class="btn btn-outline btn-secondary btn-lg">Apply to
                            Perform</a>
                    </div>
                </div>
            </div>


        </section>

        <!-- Meetups & Clubs Section -->
        <section class="mb-20 bg-warning/20 p-8 lg:p-12">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Meetups & Clubs</h2>
                <p class="text-lg max-w-3xl mx-auto">
                    Connect with like-minded musicians through our regular meetups, learning sessions, and specialty
                    clubs.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
                <!-- Real Book Club -->
                <div class="card shadow-xl border-2">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-amber-500 rounded-full flex items-center justify-center">
                                <x-unicon name="tabler:music" class="size-6 text-white" />
                            </div>
                            <h3 class="card-title text-2xl">Real Book Club</h3>
                        </div>

                        <p class="text-lg mb-4">
                            Our flagship jazz jam club where musicians of all levels come together to explore
                            the Great American Songbook and beyond.
                        </p>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <h5 class="font-bold text-amber-700">When</h5>
                                <p class="text-sm">1st Thursday of every month<br>6:30 PM - 8:00 PM</p>
                            </div>
                            <div>
                                <h5 class="font-bold text-amber-700">Format</h5>
                                <p class="text-sm">Open jam session<br>All skill levels welcome</p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="font-bold text-amber-700 mb-2">What We Do</h5>
                            <ul class="space-y-1 text-sm list-disc list-inside">
                                <li>Work through Real Book standards</li>
                                <li>Practice improvisation in a supportive environment</li>
                                <li>Connect with other jazz enthusiasts</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <x-unicon name="tabler:info-circle" class="size-4" />
                            <span class="text-sm">Bring your instrument and a Real Book (or we'll share!)</span>
                        </div>
                    </div>
                </div>

                <!-- Other Meetups -->
                <div class="space-y-6">
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-3">
                                <x-unicon name="tabler:users" class="size-6 text-primary" />
                                <h4 class="card-title">Songwriter Circle</h4>
                            </div>
                            <p class="text-sm mb-3">
                                Monthly gathering for sharing original songs, getting feedback, and collaborating on new
                                material.
                            </p>
                            <div class="badge badge-primary badge-outline">2nd Saturday • 2:00 PM</div>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-3">
                                <x-unicon name="tabler:microphone" class="size-6 text-secondary" />
                                <h4 class="card-title">Monthly Meetup</h4>
                            </div>
                            <p class="text-sm mb-3">
                                Come chat with - or just listen to - other local musicians about gear, gigs, and
                                everything music-related. Everyone is welcome!
                            </p>
                            <div class="badge badge-secondary badge-outline">Last Thursday • 6:30 PM</div>
                        </div>
                    </div>
                    {{--
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-3">
                                <x-unicon name="tabler:headphones" class="size-6 text-accent" />
                                <h4 class="card-title">Production Meetup</h4>
                            </div>
                            <p class="text-sm mb-3">
                                Learn recording techniques, share production tips, and collaborate on home recording
                                projects.
                            </p>
                            <div class="badge badge-accent badge-outline">3rd Friday • 7:00 PM</div>
                        </div>
                    </div> --}}
                </div>
            </div>

            <div class="text-center">
                <a href="{{ route('contact') }}?topic=general" class="btn btn-primary btn-lg">Join a Meetup</a>
                <a href="{{ route('members.index') }}" class="btn btn-outline btn-secondary btn-lg">Connect with
                    Members</a>
            </div>
        </section>

        <!-- Gear Lending Library Section -->
        <section class="mb-20 bg-info/20 p-8 lg:p-12">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Gear Lending Library</h2>
                <p class="text-lg max-w-3xl mx-auto">
                    Access professional music equipment through our member gear lending program. Try before you buy, or
                    use quality gear for your performances and recordings.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-12">
                <div>
                    <h3 class="text-2xl font-bold mb-4">Quality Gear When You Need It</h3>
                    <p class="text-lg mb-4">
                        Our lending library features carefully maintained instruments and equipment available to CMC
                        members for short-term use.
                    </p>
                    <p class="text-lg mb-4">
                        Perfect for trying new instruments, covering for repairs, or accessing specialized gear for
                        recording projects and performances.
                    </p>
                    <div class="alert alert-info">
                        <x-tabler-user-circle class="size-6" />
                        <span><strong>Members Only</strong><br /> Gear lending requires CMC membership and good
                            standing</span>
                    </div>
                </div>

                <div class="card bg-info text-info-content shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-center mb-4">
                            <x-tabler-guitar-pick class="size-24 opacity-30" />
                        </div>
                        <h4 class="card-title justify-center">Available Equipment</h4>
                        <ul class="space-y-2 list-disc list-inside">
                            <li>Electric guitars & basses</li>
                            <li>Acoustic instruments</li>
                            <li>Amplifiers & effects pedals</li>
                            <li>Recording equipment</li>
                            <li>Percussion instruments</li>
                            <li>Specialty instruments</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Lending Terms ---->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <h4 class="card-title justify-center">Borrowing Period</h4>
                        <div class="text-2xl font-bold text-info">1-2 Weeks</div>
                        <p class="text-sm">Renewable based on availability</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <h4 class="card-title justify-center">Security Deposit</h4>
                        <div class="text-2xl font-bold text-info">Varies</div>
                        <p class="text-sm">Refundable upon return</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <h4 class="card-title justify-center">Rental Fee</h4>
                        <div class="text-2xl font-bold text-info">Low Cost</div>
                        <p class="text-sm">Covers maintenance & replacement</p>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <a href="{{ route('contact') }}?topic=gear" class="btn btn-info btn-lg">Browse Available Gear</a>
                <a href="{{ route('contact') }}?topic=gear" class="btn btn-outline btn-info btn-lg">Donate
                    Equipment</a>
            </div>
        </section>

        <!-- Call to Action -->
        <div class="text-center bg-primary/20 rounded-lg p-12">
            <h2 class="text-4xl font-bold mb-6">Ready to Get Involved?</h2>
            <p class="text-lg mb-8 max-w-2xl mx-auto">
                Join the Corvallis Music Collective to access all our programs and connect with a vibrant community of
                musicians.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <x-unicon name="tabler:number-1" class="size-8 mx-auto" />
                        <h3 class="font-bold">Join CMC</h3>
                        <p class="text-sm">Become a member to access all programs</p>
                    </div>
                </div>

                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <x-unicon name="tabler:number-2" class="size-8 mx-auto" />
                        <h3 class="font-bold">Choose Your Path</h3>
                        <p class="text-sm">Practice, perform, or join our clubs</p>
                    </div>
                </div>

                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <x-unicon name="tabler:number-3" class="size-8 mx-auto" />
                        <h3 class="font-bold">Make Music</h3>
                        <p class="text-sm">Connect and create with the community</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/member/register" class="btn btn-primary btn-lg">Become a Member</a>
                <a href="{{ route('contact') }}?topic=general" class="btn btn-outline btn-secondary btn-lg">Ask
                    Questions</a>
            </div>
        </div>
    </div>
</x-public.layout>
