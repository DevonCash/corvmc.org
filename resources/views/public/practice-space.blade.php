<x-public.layout title="Practice Space - Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-success/10 to-warning/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Practice Space</h1>
                <p class="py-6 text-lg">
                    Professional rehearsal rooms available to CMC members
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-20">
            <div>
                <h2 class="text-4xl font-bold mb-6">Affordable Practice Space for Musicians</h2>
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
                    <h3 class="card-title">Room Features</h3>
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

        <!-- Rooms Available -->
        <div class="mb-16">
            <h2 class="text-4xl font-bold text-center mb-12">Our Practice Rooms</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card bg-base-100 shadow-lg">
                    <figure>
                        <img src="https://via.placeholder.com/400x250/8b5cf6/ffffff?text=Room+A" alt="Practice Room A" class="w-full h-48 object-cover" />
                    </figure>
                    <div class="card-body">
                        <h3 class="card-title">Room A - "The Main Stage"</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <strong>Size:</strong> 15' x 20'<br>
                                <strong>Capacity:</strong> 5-6 musicians<br>
                                <strong>Best for:</strong> Full bands
                            </div>
                            <div>
                                <strong>Equipment:</strong><br>
                                • 12-channel mixer<br>
                                • Full drum kit<br>
                                • Guitar/bass amps<br>
                                • 4 vocal mics
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card bg-base-100 shadow-lg">
                    <figure>
                        <img src="https://via.placeholder.com/400x250/06b6d4/ffffff?text=Room+B" alt="Practice Room B" class="w-full h-48 object-cover" />
                    </figure>
                    <div class="card-body">
                        <h3 class="card-title">Room B - "The Studio"</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <strong>Size:</strong> 12' x 16'<br>
                                <strong>Capacity:</strong> 3-4 musicians<br>
                                <strong>Best for:</strong> Small groups
                            </div>
                            <div>
                                <strong>Equipment:</strong><br>
                                • 8-channel mixer<br>
                                • Compact drum kit<br>
                                • Combo amps<br>
                                • 2 vocal mics
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing -->
        <div class="mb-16">
            <h2 class="text-4xl font-bold text-center mb-12">Pricing</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center text-2xl">Standard Rate</h3>
                        <div class="text-4xl font-bold text-primary my-4">$30/hour</div>
                        <ul class="text-left space-y-2">
                            <li>• Any practice room</li>
                            <li>• All equipment included</li>
                            <li>• Book up to 4 hours/day</li>
                            <li>• 24-hour cancellation</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card bg-primary text-primary-content shadow-lg">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center text-2xl">Sustaining Members</h3>
                        <div class="text-4xl font-bold my-4">4 Free Hours</div>
                        <div class="text-lg mb-4">+ $12/hour after</div>
                        <ul class="text-left space-y-2">
                            <li>• Monthly free hours</li>
                            <li>• Priority booking</li>
                            <li>• Discounted rates</li>
                            <li>• Special events access</li>
                        </ul>
                        <div class="card-actions justify-center mt-4">
                            <a href="{{ route('support') }}" class="btn btn-secondary">Learn More</a>
                        </div>
                    </div>
                </div>
                
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center text-2xl">Block Packages</h3>
                        <div class="text-4xl font-bold text-accent my-4">$25/hour</div>
                        <div class="text-lg mb-4">Member discounts</div>
                        <ul class="text-left space-y-2">
                            <li>• Reduced rate for members</li>
                            <li>• Priority booking access</li>
                            <li>• Flexible scheduling</li>
                            <li>• Community gear access</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Policies & Guidelines -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
            <div class="card bg-base-100 shadow-lg">
                <div class="card-body">
                    <h3 class="card-title text-2xl mb-4">Booking Policies</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-2">
                            <span class="text-success">✓</span>
                            <span>Members can book up to 2 weeks in advance</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-success">✓</span>
                            <span>Maximum 4 hours per booking session</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-success">✓</span>
                            <span>24-hour cancellation required for refund</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-success">✓</span>
                            <span>Late arrivals forfeit unused time</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-success">✓</span>
                            <span>Clean up after your session</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card bg-base-100 shadow-lg">
                <div class="card-body">
                    <h3 class="card-title text-2xl mb-4">Room Guidelines</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-2">
                            <span class="text-warning">⚠</span>
                            <span>No food or drinks near equipment</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-warning">⚠</span>
                            <span>Report any equipment issues immediately</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-warning">⚠</span>
                            <span>Respect volume levels in shared building</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-warning">⚠</span>
                            <span>Lock doors when leaving</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-error">✗</span>
                            <span>No smoking or vaping in facility</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Equipment Details -->
        <div class="mb-16">
            <h2 class="text-4xl font-bold text-center mb-12">What's Included</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="card bg-base-200">
                    <div class="card-body text-center">
                        <div class="text-4xl mb-4">🥁</div>
                        <h3 class="font-bold">Drum Kits</h3>
                        <p class="text-sm">5-piece acoustic sets with hardware. Bring your own cymbals and sticks.</p>
                    </div>
                </div>
                
                <div class="card bg-base-200">
                    <div class="card-body text-center">
                        <div class="text-4xl mb-4">🎸</div>
                        <h3 class="font-bold">Amplifiers</h3>
                        <p class="text-sm">Guitar and bass amps ranging from 50W to 100W. Cables provided.</p>
                    </div>
                </div>
                
                <div class="card bg-base-200">
                    <div class="card-body text-center">
                        <div class="text-4xl mb-4">🎤</div>
                        <h3 class="font-bold">PA System</h3>
                        <p class="text-sm">Full sound system with monitors, mics, and mixing board.</p>
                    </div>
                </div>
                
                <div class="card bg-base-200">
                    <div class="card-body text-center">
                        <div class="text-4xl mb-4">🔌</div>
                        <h3 class="font-bold">Accessories</h3>
                        <p class="text-sm">Cables, stands, power strips, and basic recording interface.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- How to Get Access -->
        <div class="text-center bg-gradient-to-r from-primary/10 to-secondary/10 rounded-lg p-12">
            <h2 class="text-4xl font-bold mb-6">Ready to Start Practicing?</h2>
            <p class="text-lg mb-8 max-w-2xl mx-auto">
                Join the Corvallis Music Collective to gain access to our practice rooms, 
                connect with other musicians, and be part of our vibrant community.
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <div class="text-3xl mb-2">1️⃣</div>
                        <h3 class="font-bold">Join CMC</h3>
                        <p class="text-sm">Become a member to access practice spaces</p>
                    </div>
                </div>
                
                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <div class="text-3xl mb-2">2️⃣</div>
                        <h3 class="font-bold">Book Online</h3>
                        <p class="text-sm">Use our member portal to reserve time slots</p>
                    </div>
                </div>
                
                <div class="card bg-base-100">
                    <div class="card-body text-center">
                        <div class="text-3xl mb-2">3️⃣</div>
                        <h3 class="font-bold">Rock Out</h3>
                        <p class="text-sm">Show up and make some music!</p>
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