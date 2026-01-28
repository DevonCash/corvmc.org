<x-public.layout title="Contact CMC - Get in Touch | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-success/10">
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
        @if (session('success'))
            <div class="alert alert-success max-w-2xl mx-auto mb-8">
                <x-icon name="tabler-check-circle" class="size-6" />
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Contact Form -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-6">Send us a Message</h2>
                    @livewire('contact-form')
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
                                <x-tabler-map-pin class="size-5 mt-1 text-primary" />
                                <div>
                                    <strong>Address</strong><br>
                                    6775 A Philomath Blvd<br>
                                    Corvallis, OR 97333
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <x-tabler-clock class="size-5 mt-1 text-primary" />
                                <div>
                                    <strong>Office Hours</strong><br>
                                    By appointment only
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
                            <a href="mailto:contact@corvmc.org"
                                class="btn btn-outline btn-primary w-full justify-start">
                                <x-tabler-mail class="size-5 mr-3" />
                                contact@corvmc.org
                            </a>

                            {{-- <a href="tel:+15415551234" class="btn btn-outline btn-secondary w-full justify-start">
                                <x-tabler-phone class="size-5 mr-3" />
                                (541) 555-1234
                            </a> --}}
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
                                    <p>You can join by registering online through our member portal. </p>
                                </div>
                            </details>

                            <details class="collapse collapse-arrow">
                                <summary class="collapse-title font-medium">Can I book practice space as a non-member?
                                </summary>
                                <div class="collapse-content">
                                    <p>Practice space access is exclusively for CMC members, membership is free!</p>
                                </div>
                            </details>

                            <details class="collapse collapse-arrow">
                                <summary class="collapse-title font-medium">How do I submit music for events?</summary>
                                <div class="collapse-content">
                                    <p>Send us your music through the contact form above, selecting "Performance
                                        Inquiry".</p>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public.layout>
