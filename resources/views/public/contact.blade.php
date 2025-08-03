<x-public.layout title="Contact Us - Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-info/10 to-success/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Get in Touch</h1>
                <p class="py-6 text-lg">
                    We'd love to hear from you! Reach out with questions, performance inquiries, or just to say hello.
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16">
        @if(session('success'))
        <div class="alert alert-success max-w-2xl mx-auto mb-8">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Contact Form -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-6">Send us a Message</h2>
                    
                    <form action="{{ route('contact.store') }}" method="POST" class="space-y-4">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">First Name *</span>
                                </label>
                                <input type="text" name="first_name" class="input input-bordered" required value="{{ old('first_name') }}" />
                            </div>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Last Name *</span>
                                </label>
                                <input type="text" name="last_name" class="input input-bordered" required value="{{ old('last_name') }}" />
                            </div>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Email *</span>
                            </label>
                            <input type="email" name="email" class="input input-bordered" required value="{{ old('email') }}" />
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Phone</span>
                            </label>
                            <input type="tel" name="phone" class="input input-bordered" value="{{ old('phone') }}" />
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Subject *</span>
                            </label>
                            <select name="subject" class="select select-bordered" required>
                                <option value="">Choose a topic...</option>
                                <option value="general">General Inquiry</option>
                                <option value="membership">Membership Questions</option>
                                <option value="practice_space">Practice Space</option>
                                <option value="performance">Performance Inquiry</option>
                                <option value="volunteer">Volunteer Opportunities</option>
                                <option value="donation">Donations & Support</option>
                            </select>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Message *</span>
                            </label>
                            <textarea name="message" class="textarea textarea-bordered h-32" placeholder="Tell us more about your inquiry..." required>{{ old('message') }}</textarea>
                        </div>
                        
                        <div class="card-actions justify-center mt-6">
                            <button type="submit" class="btn btn-primary btn-lg">Send Message</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="space-y-8">
                <!-- Location & Hours -->
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title text-xl mb-4">Visit Us</h3>
                        
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 mt-1 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <div>
                                    <strong>Address</strong><br>
                                    6775 A Philomath Blvd<br>
                                    Corvallis, OR 97333
                                </div>
                            </div>
                            
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 mt-1 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <strong>Office Hours</strong><br>
                                    Monday - Friday: 10am - 6pm<br>
                                    Saturday: 10am - 4pm<br>
                                    Sunday: Closed
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Contact -->
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title text-xl mb-4">Quick Contact</h3>
                        
                        <div class="space-y-3">
                            <a href="mailto:hello@corvallismusic.org" class="btn btn-outline btn-primary w-full justify-start">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                hello@corvallismusic.org
                            </a>
                            
                            <a href="tel:+15415551234" class="btn btn-outline btn-secondary w-full justify-start">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                (541) 555-1234
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- FAQ Quick Links -->
                <div class="card bg-base-200">
                    <div class="card-body">
                        <h3 class="card-title text-xl mb-4">Quick Answers</h3>
                        
                        <div class="space-y-3">
                            <details class="collapse collapse-arrow">
                                <summary class="collapse-title font-medium">How do I become a member?</summary>
                                <div class="collapse-content">
                                    <p>You can join by registering online through our member portal. Basic membership starts at $15/month.</p>
                                </div>
                            </details>
                            
                            <details class="collapse collapse-arrow">
                                <summary class="collapse-title font-medium">Can I book practice space as a non-member?</summary>
                                <div class="collapse-content">
                                    <p>Practice space access is exclusively for CMC members.</p>
                                </div>
                            </details>
                            
                            <details class="collapse collapse-arrow">
                                <summary class="collapse-title font-medium">How do I submit music for events?</summary>
                                <div class="collapse-content">
                                    <p>Send us your music through the contact form above, selecting "Performance Inquiry".</p>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public.layout>