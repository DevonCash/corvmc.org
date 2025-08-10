<x-public.layout title="Upcoming Music Events & Shows | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-secondary/10 to-accent/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Upcoming Events</h1>
                <p class="py-6 text-lg">
                    Discover amazing live music and community events happening at CMC
                </p>
            </div>
        </div>
    </div>

    @livewire('events-grid')

    <!-- Call to Action -->
    <div class="container mx-auto px-4">
        <div class="text-center mt-8 bg-base-200 rounded-lg p-8">
            <h2 class="text-3xl font-bold mb-4">Want to Perform at CMC?</h2>
            <p class="text-lg mb-6">
                We're always looking for talented local musicians to showcase at our events.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('contact') }}?topic=performance" class="btn btn-primary">Submit Performance Inquiry</a>
                <a href="/member/register" class="btn btn-outline btn-secondary">Become a Member</a>
            </div>
        </div>
    </div>
</x-public.layout>
