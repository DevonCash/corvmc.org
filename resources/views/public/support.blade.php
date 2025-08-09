<x-public.layout title="Support Local Music - Donate to CMC | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-accent/10 to-success/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Support Our Mission</h1>
                <p class="py-6 text-lg">
                    Help us keep music accessible and build a stronger community for all musicians
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Impact Section -->
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold mb-6">Your Impact</h2>
            <p class="text-lg mb-8 max-w-3xl mx-auto">
                Every donation directly supports our mission to make music accessible to everyone in our community. 
                Here's how your contribution makes a difference:
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="stat bg-primary text-primary-content rounded-lg">
                    <div class="stat-title text-primary-content/70">$25</div>
                    <div class="stat-value text-2xl">2 Hours</div>
                    <div class="stat-desc text-primary-content/70">Practice time for a musician</div>
                </div>
                
                <div class="stat bg-secondary text-secondary-content rounded-lg">
                    <div class="stat-title text-secondary-content/70">$50</div>
                    <div class="stat-value text-2xl">Workshop</div>
                    <div class="stat-desc text-secondary-content/70">Educational program for 10 people</div>
                </div>
                
                <div class="stat bg-accent text-accent-content rounded-lg">
                    <div class="stat-title text-accent-content/70">$100</div>
                    <div class="stat-value text-2xl">Event</div>
                    <div class="stat-desc text-accent-content/70">Community showcase for local bands</div>
                </div>
                
                <div class="stat bg-success text-success-content rounded-lg">
                    <div class="stat-title text-success-content/70">$250</div>
                    <div class="stat-value text-2xl">Equipment</div>
                    <div class="stat-desc text-success-content/70">New gear for practice rooms</div>
                </div>
            </div>
        </div>

        <!-- Donation Options -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
            <!-- One-Time Donation -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-4">One-Time Donation</h2>
                    <p class="mb-6">Make a single contribution to support our ongoing programs and facility maintenance.</p>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <button class="btn btn-outline btn-primary">$25</button>
                        <button class="btn btn-outline btn-primary">$50</button>
                        <button class="btn btn-outline btn-primary">$100</button>
                        <button class="btn btn-outline btn-primary">$250</button>
                    </div>
                    
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text">Custom Amount</span>
                        </label>
                        <input type="number" placeholder="Enter amount" class="input input-bordered" />
                    </div>
                    
                    <button class="btn btn-primary btn-block">Donate Now</button>
                </div>
            </div>

            <!-- Monthly Support -->
            <div class="card bg-primary text-primary-content shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-4">Become a Sustaining Member</h2>
                    <p class="mb-6">Join our monthly giving program and get exclusive benefits while supporting our mission year-round.</p>
                    
                    <div class="space-y-4 mb-6">
                        <div class="card bg-primary-content text-primary">
                            <div class="card-body py-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="font-bold">Supporter</h3>
                                        <p class="text-sm">2 free practice hours/month</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl font-bold">$15</span>
                                        <p class="text-sm">/month</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card bg-secondary text-secondary-content">
                            <div class="card-body py-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="font-bold">Champion</h3>
                                        <p class="text-sm">4 free hours + event priority</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl font-bold">$25</span>
                                        <p class="text-sm">/month</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card bg-accent text-accent-content">
                            <div class="card-body py-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="font-bold">Patron</h3>
                                        <p class="text-sm">8 free hours + exclusive events</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl font-bold">$50</span>
                                        <p class="text-sm">/month</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-secondary btn-block">Start Monthly Support</button>
                </div>
            </div>
        </div>

        <!-- Equipment Wishlist -->
        <div class="mb-16">
            <h2 class="text-4xl font-bold text-center mb-8">Equipment Wishlist</h2>
            <p class="text-center text-lg mb-8">Help us upgrade our practice rooms with these needed items:</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Digital Mixing Board</h3>
                        <div class="flex justify-between items-center mb-2">
                            <span>$1,200 goal</span>
                            <span>$300 raised</span>
                        </div>
                        <progress class="progress progress-primary" value="25" max="100"></progress>
                        <p class="text-sm mt-2">Professional 16-channel mixer for Room A</p>
                        <div class="card-actions justify-end mt-4">
                            <button class="btn btn-primary btn-sm">Contribute</button>
                        </div>
                    </div>
                </div>
                
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Drum Kit Upgrade</h3>
                        <div class="flex justify-between items-center mb-2">
                            <span>$800 goal</span>
                            <span>$650 raised</span>
                        </div>
                        <progress class="progress progress-secondary" value="81" max="100"></progress>
                        <p class="text-sm mt-2">New cymbals and hardware for shared kit</p>
                        <div class="card-actions justify-end mt-4">
                            <button class="btn btn-secondary btn-sm">Contribute</button>
                        </div>
                    </div>
                </div>
                
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title">Recording Interface</h3>
                        <div class="flex justify-between items-center mb-2">
                            <span>$500 goal</span>
                            <span>$0 raised</span>
                        </div>
                        <progress class="progress progress-accent" value="0" max="100"></progress>
                        <p class="text-sm mt-2">Multi-track recording capability for demos</p>
                        <div class="card-actions justify-end mt-4">
                            <button class="btn btn-accent btn-sm">Contribute</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Other Ways to Support -->
        <div class="bg-base-200 rounded-lg p-8 mb-16">
            <h2 class="text-3xl font-bold text-center mb-8">Other Ways to Support</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-4xl mb-4"><x-unicon name="tabler:music" class="size-10" /></div>
                    <h3 class="font-bold mb-2">Attend Events</h3>
                    <p class="text-sm">Come to our shows and bring friends to support local music.</p>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl mb-4"><x-unicon name="tabler:speakerphone" class="size-10" /></div>
                    <h3 class="font-bold mb-2">Spread the Word</h3>
                    <p class="text-sm">Share our mission on social media and tell other musicians about us.</p>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl mb-4"><x-unicon name="tabler:tools" class="size-10" /></div>
                    <h3 class="font-bold mb-2">Volunteer</h3>
                    <p class="text-sm">Donate your time and skills to help with events and maintenance.</p>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl mb-4"><x-unicon name="tabler:gift" class="size-10" /></div>
                    <h3 class="font-bold mb-2">In-Kind Donations</h3>
                    <p class="text-sm">Donate equipment, services, or supplies we need.</p>
                </div>
            </div>
        </div>

        <!-- Transparency -->
        <div class="text-center">
            <h2 class="text-3xl font-bold mb-6">Financial Transparency</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat bg-base-200 rounded-lg">
                    <div class="stat-title">Program Expenses</div>
                    <div class="stat-value text-primary">85%</div>
                    <div class="stat-desc">Directly funds our mission</div>
                </div>
                
                <div class="stat bg-base-200 rounded-lg">
                    <div class="stat-title">Administrative</div>
                    <div class="stat-value text-secondary">10%</div>
                    <div class="stat-desc">Operations and management</div>
                </div>
                
                <div class="stat bg-base-200 rounded-lg">
                    <div class="stat-title">Fundraising</div>
                    <div class="stat-value text-accent">5%</div>
                    <div class="stat-desc">Growing our impact</div>
                </div>
            </div>
            
            <p class="text-lg mb-4">
                <strong>Tax Deductible:</strong> CMC is a 501(c)(3) nonprofit organization. 
                Your donation is tax-deductible to the full extent allowed by law.
            </p>
            <p class="text-sm opacity-70">EIN: {{ app(\App\Settings\OrganizationSettings::class)->getFormattedEin() }}</p>
        </div>
    </div>
</x-public.layout>