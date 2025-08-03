<x-public.layout title="Contribute to CMC - Support Local Music | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-accent/10 to-success/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Help Support Our Mission</h1>
                <p class="py-6 text-lg">
                    There are many ways to contribute to the Corvallis Music Collective and help us build a stronger music community
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        <!-- Main contribution options -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
            <!-- Volunteer -->
            <div class="card bg-primary text-primary-content shadow-xl">
                <div class="card-body">
                    <div class="flex items-center gap-4 mb-4">
                        <x-unicon name="tabler:heart-handshake" class="size-12" />
                        <h2 class="card-title text-3xl">Volunteer Your Time</h2>
                    </div>
                    <p class="text-lg mb-6">
                        Join our team of volunteers and make a direct impact on our community. From event support to facility maintenance, there are opportunities for everyone.
                    </p>
                    
                    <!-- Key volunteer opportunities -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="bg-primary-content text-primary rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <x-unicon name="tabler:microphone" class="size-5" />
                                <h3 class="font-bold">Event Support</h3>
                            </div>
                            <p class="text-sm">Help with concerts, setup, and crowd management</p>
                        </div>
                        <div class="bg-primary-content text-primary rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <x-unicon name="tabler:device-mobile" class="size-5" />
                                <h3 class="font-bold">Social Media</h3>
                            </div>
                            <p class="text-sm">Create content and spread the word about CMC</p>
                        </div>
                        <div class="bg-primary-content text-primary rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <x-unicon name="tabler:tool" class="size-5" />
                                <h3 class="font-bold">Facility Care</h3>
                            </div>
                            <p class="text-sm">Maintain our practice rooms and equipment</p>
                        </div>
                        <div class="bg-primary-content text-primary rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <x-unicon name="tabler:pencil" class="size-5" />
                                <h3 class="font-bold">Grant Writing</h3>
                            </div>
                            <p class="text-sm">Help secure funding for our programs</p>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <a href="{{ route('volunteer') }}" class="btn btn-secondary btn-lg flex-1">Learn More About Volunteering</a>
                    </div>
                </div>
            </div>

            <!-- Donate -->
            <div class="card bg-secondary text-secondary-content shadow-xl">
                <div class="card-body">
                    <div class="flex items-center gap-4 mb-4">
                        <x-unicon name="tabler:heart" class="size-12" />
                        <h2 class="card-title text-3xl">Make a Donation</h2>
                    </div>
                    <p class="text-lg mb-6">
                        Your financial support helps us maintain affordable practice space, host events, and keep music accessible to everyone in our community.
                    </p>
                    
                    <!-- Impact stats based on wishlist -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-secondary-content text-secondary rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold">$50</div>
                            <div class="text-sm">Window Signage</div>
                        </div>
                        <div class="bg-secondary-content text-secondary rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold">$200</div>
                            <div class="text-sm">Used iPhone for Tap-to-Pay</div>
                        </div>
                        <div class="bg-secondary-content text-secondary rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold">$300</div>
                            <div class="text-sm">Professional Storefront Sign</div>
                        </div>
                        <div class="bg-secondary-content text-secondary rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold">$800</div>
                            <div class="text-sm">Security System</div>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <a href="{{ route('support') }}" class="btn btn-primary btn-lg flex-1">Donate Now</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Needs & Wishlist -->
        <div class="mb-16">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Current Needs & Wishlist</h2>
                <p class="text-lg opacity-70">Help us improve our community space with these specific items</p>
            </div>
            
            <!-- High Priority Immediate Needs -->
            <div class="mb-12">
                <h3 class="text-2xl font-bold mb-6 flex items-center gap-3">
                    <x-unicon name="tabler:flame" class="size-7 text-error" />
                    Immediate Venue Needs (High Priority)
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="card bg-base-100 shadow-lg border-l-4 border-error">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:window" class="size-8 text-primary" />
                                <h4 class="card-title text-lg">Professional Window Signage</h4>
                            </div>
                            <div class="text-2xl font-bold text-primary mb-2">$200-600</div>
                            <p class="text-sm opacity-70">Vinyl lettering/graphics for storefront visibility</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-primary btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg border-l-4 border-error">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:key" class="size-8 text-secondary" />
                                <h4 class="card-title text-lg">Keyless Entry System</h4>
                            </div>
                            <div class="text-2xl font-bold text-secondary mb-2">$150-500</div>
                            <p class="text-sm opacity-70">Code-controlled, remote programming preferred</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-secondary btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg border-l-4 border-error">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:video" class="size-8 text-accent" />
                                <h4 class="card-title text-lg">Security Camera System</h4>
                            </div>
                            <div class="text-2xl font-bold text-accent mb-2">$300-800</div>
                            <p class="text-sm opacity-70">Indoor/outdoor coverage with remote monitoring</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-accent btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg border-l-4 border-error">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:device-mobile" class="size-8 text-warning" />
                                <h4 class="card-title text-lg">Used iPhone with NFC</h4>
                            </div>
                            <div class="text-2xl font-bold text-warning mb-2">$200-400</div>
                            <p class="text-sm opacity-70">For tap-to-pay via Zeffy (huge operational upgrade!)</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-warning btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg border-l-4 border-error">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:bulb" class="size-8 text-info" />
                                <h4 class="card-title text-lg">Modern LED Stage Lighting</h4>
                            </div>
                            <div class="text-2xl font-bold text-info mb-2">$300-800</div>
                            <p class="text-sm opacity-70">Smart/home automation compatible</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-info btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg border-l-4 border-error">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:printer" class="size-8 text-success" />
                                <h4 class="card-title text-lg">Epson EcoTank Printer</h4>
                            </div>
                            <div class="text-2xl font-bold text-success mb-2">$300-400</div>
                            <p class="text-sm opacity-70">ET-3850 for high-volume poster printing</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-success btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Audio Equipment -->
            <div class="mb-12">
                <h3 class="text-2xl font-bold mb-6 flex items-center gap-3">
                    <x-unicon name="tabler:music" class="size-7 text-primary" />
                    Sound & Audio Equipment
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:device-audio-tape" class="size-8 text-primary" />
                                <h4 class="card-title text-lg">Digital Mixer</h4>
                            </div>
                            <div class="text-2xl font-bold text-primary mb-2">$400-800</div>
                            <p class="text-sm opacity-70">Replace loaned MR18 - Behringer XR18 or Midas MR18</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-primary btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:microphone" class="size-8 text-secondary" />
                                <h4 class="card-title text-lg">Basic Microphones</h4>
                            </div>
                            <div class="text-2xl font-bold text-secondary mb-2">$50-100</div>
                            <p class="text-sm opacity-70">SM57/58 style mics, used condition OK</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-secondary btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:vinyl" class="size-8 text-accent" />
                                <h4 class="card-title text-lg">Used Turntable</h4>
                            </div>
                            <div class="text-2xl font-bold text-accent mb-2">$100-300</div>
                            <p class="text-sm opacity-70">Direct drive preferred for obsolete media library</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-accent btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Facility & Infrastructure -->
            <div>
                <h3 class="text-2xl font-bold mb-6 flex items-center gap-3">
                    <x-unicon name="tabler:building" class="size-7 text-warning" />
                    Facility Improvements
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:paint" class="size-8 text-primary" />
                                <h4 class="card-title text-lg">Stage Paint & Supplies</h4>
                            </div>
                            <div class="text-2xl font-bold text-primary mb-2">$50-100</div>
                            <p class="text-sm opacity-70">Black or dark gray paint for stage area</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-primary btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:armchair" class="size-8 text-secondary" />
                                <h4 class="card-title text-lg">Counter Height Stools</h4>
                            </div>
                            <div class="text-2xl font-bold text-secondary mb-2">$100-400</div>
                            <p class="text-sm opacity-70">2-4 stools for retail/merchandise area</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-secondary btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-base-100 shadow-lg">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <x-unicon name="tabler:tools" class="size-8 text-accent" />
                                <h4 class="card-title text-lg">Workshop Setup</h4>
                            </div>
                            <div class="text-2xl font-bold text-accent mb-2">$200-600</div>
                            <p class="text-sm opacity-70">Workbench, tool organization, soldering station</p>
                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('support') }}" class="btn btn-accent btn-sm">Contribute</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- In-Kind Donations -->
        <div class="mb-16">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">In-Kind Donations We Need</h2>
                <p class="text-lg opacity-70">Services, materials, and time donations are just as valuable</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <x-unicon name="tabler:briefcase" class="size-8 text-primary" />
                            <h3 class="card-title">Professional Services</h3>
                        </div>
                        <ul class="space-y-2 text-sm">
                            <li>• Legal services (contracts, liability)</li>
                            <li>• Accounting/bookkeeping</li>
                            <li>• Graphic design (promotional materials)</li>
                            <li>• Web development</li>
                            <li>• Photography/videography</li>
                            <li>• Workshop setup assistance</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <x-unicon name="tabler:package" class="size-8 text-secondary" />
                            <h3 class="card-title">Materials & Supplies</h3>
                        </div>
                        <ul class="space-y-2 text-sm">
                            <li>• Office supplies (paper, pens, folders)</li>
                            <li>• Cleaning supplies</li>
                            <li>• Workshop consumables (solder, flux, heat shrink)</li>
                            <li>• Gift cards (hardware stores, music shops)</li>
                            <li>• Vinyl & CD donations</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <x-unicon name="tabler:clock" class="size-8 text-accent" />
                            <h3 class="card-title">Time & Skills</h3>
                        </div>
                        <ul class="space-y-2 text-sm">
                            <li>• Volunteer coordination</li>
                            <li>• Social media management</li>
                            <li>• Event setup/breakdown</li>
                            <li>• Equipment maintenance & repair</li>
                            <li>• Music instruction (workshops, lessons)</li>
                            <li>• Guitar setup & repair training</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Other Ways to Help -->
        <div class="bg-base-200 rounded-lg p-8 mb-16">
            <h2 class="text-3xl font-bold text-center mb-8">Other Ways to Help</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-4xl mb-4"><x-unicon name="tabler:music" class="size-10 mx-auto" /></div>
                    <h3 class="font-bold mb-2">Attend Events</h3>
                    <p class="text-sm">Come to our shows and bring friends to support local music.</p>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl mb-4"><x-unicon name="tabler:speakerphone" class="size-10 mx-auto" /></div>
                    <h3 class="font-bold mb-2">Spread the Word</h3>
                    <p class="text-sm">Share our mission on social media and tell other musicians about us.</p>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl mb-4"><x-unicon name="tabler:users" class="size-10 mx-auto" /></div>
                    <h3 class="font-bold mb-2">Become a Member</h3>
                    <p class="text-sm">Join our community and participate in programs and events.</p>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl mb-4"><x-unicon name="tabler:gift" class="size-10 mx-auto" /></div>
                    <h3 class="font-bold mb-2">In-Kind Donations</h3>
                    <p class="text-sm">Donate equipment, services, or supplies we need.</p>
                </div>
            </div>
        </div>

        <!-- Get Started -->
        <div class="text-center">
            <h2 class="text-3xl font-bold mb-6">Ready to Get Involved?</h2>
            <p class="text-lg mb-8 max-w-2xl mx-auto">
                Whether you have time to volunteer or want to make a financial contribution, every bit of support helps us build a stronger music community.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('volunteer') }}" class="btn btn-primary btn-lg">
                    <x-unicon name="tabler:heart-handshake" class="size-5 mr-2" />
                    Start Volunteering
                </a>
                <a href="{{ route('support') }}" class="btn btn-secondary btn-lg">
                    <x-unicon name="tabler:heart" class="size-5 mr-2" />
                    Make a Donation
                </a>
                <a href="{{ route('contact') }}" class="btn btn-outline btn-accent btn-lg">
                    <x-unicon name="tabler:mail" class="size-5 mr-2" />
                    Contact Us
                </a>
            </div>
            
            <!-- Contact info -->
            <div class="mt-8 p-4 bg-base-200 rounded-lg max-w-md mx-auto">
                <p class="text-sm opacity-70 mb-2">For donations and questions:</p>
                <a href="mailto:donations@corvallismusiccollective.org" class="text-primary font-semibold">
                    donations@corvallismusiccollective.org
                </a>
            </div>
        </div>
    </div>
</x-public.layout>