<x-public.layout title="Programs | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-success/10 to-warning/10">
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
        <section class="mb-20 bg-gradient-to-br from-success/5 to-success/10 rounded-3xl p-8 lg:p-12">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Practice Space</h2>
                <p class="text-lg max-w-3xl mx-auto">
                    Professional rehearsal rooms available to CMC members, equipped with all the gear you need to develop your craft.
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
                        Members can book hourly sessions in our soundproofed rooms, complete with
                        PA systems, microphones, and all the essentials for a productive practice session.
                    </p>
                    <div class="alert alert-info">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><strong>Members Only:</strong> Practice space access requires CMC membership</span>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-xl">
                    <figure>
                        <img src="https://via.placeholder.com/400x300/f59e0b/ffffff?text=Practice+Room" alt="Practice Room" class="w-full h-64 object-cover" />
                    </figure>
                    <div class="card-body">
                        <h4 class="card-title">Room Features</h4>
                        <ul class="space-y-2">
                            <li>• Soundproofed walls</li>
                            <li>• Full PA system</li>
                            <li>• Microphones & stands</li>
                            <li>• Drum kit (cymbals & hardware)</li>
                            <li>• Guitar & bass amplifiers</li>
                            <li>• Comfortable seating</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Pricing Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <h4 class="card-title justify-center">Standard Rate</h4>
                        <div class="text-2xl font-bold text-primary">$30/hour</div>
                        <p class="text-sm">All equipment included</p>
                    </div>
                </div>
                <div class="card bg-primary text-primary-content shadow-lg">
                    <div class="card-body text-center">
                        <h4 class="card-title justify-center">Sustaining Members</h4>
                        <div class="text-2xl font-bold">4 Free Hours</div>
                        <p class="text-sm">+ $12/hour after</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <h4 class="card-title justify-center">Member Rate</h4>
                        <div class="text-2xl font-bold text-accent">$25/hour</div>
                        <p class="text-sm">Discounted member pricing</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Shows/Performances Section -->
        <section class="mb-20 bg-gradient-to-br from-primary/5 to-secondary/10 rounded-3xl p-8 lg:p-12">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Shows & Performances</h2>
                <p class="text-lg max-w-3xl mx-auto">
                    Showcase your talent and connect with the community through our regular performance opportunities and special events.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-12">
                <div class="card bg-base-100 shadow-xl">
                    <figure>
                        <img src="https://via.placeholder.com/400x300/8b5cf6/ffffff?text=Live+Performance" alt="Live Performance" class="w-full h-64 object-cover" />
                    </figure>
                    <div class="card-body">
                        <h4 class="card-title">Performance Opportunities</h4>
                        <ul class="space-y-2">
                            <li>• Monthly showcase events</li>
                            <li>• Open mic nights</li>
                            <li>• Collaborative performances</li>
                            <li>• Community festivals</li>
                            <li>• Recording showcases</li>
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
                    
                    <div class="grid grid-cols-2 gap-4">
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
                    </div>
                </div>
            </div>

            <div class="text-center">
                <a href="{{ route('events.index') }}" class="btn btn-primary btn-lg">View Upcoming Shows</a>
                <a href="{{ route('contact') }}" class="btn btn-outline btn-secondary btn-lg">Apply to Perform</a>
            </div>
        </section>

        <!-- Meetups & Clubs Section -->
        <section class="mb-20 bg-gradient-to-br from-accent/5 to-warning/10 rounded-3xl p-8 lg:p-12">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Meetups & Clubs</h2>
                <p class="text-lg max-w-3xl mx-auto">
                    Connect with like-minded musicians through our regular meetups, learning sessions, and specialty clubs.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
                <!-- Real Book Club -->
                <div class="card bg-gradient-to-br from-amber/10 to-orange/10 shadow-xl border-2 border-amber/20">
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
                                <p class="text-sm">Every other Thursday<br>7:00 PM - 9:30 PM</p>
                            </div>
                            <div>
                                <h5 class="font-bold text-amber-700">Format</h5>
                                <p class="text-sm">Open jam session<br>All skill levels welcome</p>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="font-bold text-amber-700 mb-2">What We Do</h5>
                            <ul class="space-y-1 text-sm">
                                <li>• Work through Real Book standards</li>
                                <li>• Learn jazz fundamentals</li>
                                <li>• Practice improvisation in a supportive environment</li>
                                <li>• Connect with other jazz enthusiasts</li>
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
                                Monthly gathering for sharing original songs, getting feedback, and collaborating on new material.
                            </p>
                            <div class="badge badge-primary badge-outline">2nd Saturday • 2:00 PM</div>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-3">
                                <x-unicon name="tabler:microphone" class="size-6 text-secondary" />
                                <h4 class="card-title">Vocal Workshop</h4>
                            </div>
                            <p class="text-sm mb-3">
                                Technique sessions, group warm-ups, and performance coaching for vocalists of all styles.
                            </p>
                            <div class="badge badge-secondary badge-outline">1st Wednesday • 6:30 PM</div>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-3">
                                <x-unicon name="tabler:headphones" class="size-6 text-accent" />
                                <h4 class="card-title">Production Meetup</h4>
                            </div>
                            <p class="text-sm mb-3">
                                Learn recording techniques, share production tips, and collaborate on home recording projects.
                            </p>
                            <div class="badge badge-accent badge-outline">3rd Friday • 7:00 PM</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <a href="{{ route('contact') }}" class="btn btn-primary btn-lg">Join a Meetup</a>
                <a href="{{ route('members.index') }}" class="btn btn-outline btn-secondary btn-lg">Connect with Members</a>
            </div>
        </section>

        <!-- Call to Action -->
        <div class="text-center bg-gradient-to-r from-primary/10 to-secondary/10 rounded-lg p-12">
            <h2 class="text-4xl font-bold mb-6">Ready to Get Involved?</h2>
            <p class="text-lg mb-8 max-w-2xl mx-auto">
                Join the Corvallis Music Collective to access all our programs and connect with a vibrant community of musicians.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <div class="text-3xl mb-2"><x-unicon name="tabler:number-1" class="size-8" /></div>
                        <h3 class="font-bold">Join CMC</h3>
                        <p class="text-sm">Become a member to access all programs</p>
                    </div>
                </div>

                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <div class="text-3xl mb-2"><x-unicon name="tabler:number-2" class="size-8" /></div>
                        <h3 class="font-bold">Choose Your Path</h3>
                        <p class="text-sm">Practice, perform, or join our clubs</p>
                    </div>
                </div>

                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <div class="text-3xl mb-2"><x-unicon name="tabler:number-3" class="size-8" /></div>
                        <h3 class="font-bold">Make Music</h3>
                        <p class="text-sm">Connect and create with the community</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/member/register" class="btn btn-primary btn-lg">Become a Member</a>
                <a href="{{ route('contact') }}" class="btn btn-outline btn-secondary btn-lg">Ask Questions</a>
            </div>
        </div>
    </div>
</x-public.layout>