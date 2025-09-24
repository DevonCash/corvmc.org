<x-public.layout title="Local Bands Directory | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-primary/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Local Bands</h1>
                <p class="py-6 text-lg">
                    Discover the amazing bands that call Corvallis Music Collective home
                </p>
            </div>
        </div>
    </div>

    @livewire('bands-grid')

    <!-- Call to Action -->
    <div class="container mx-auto px-4">
        <div class="text-center mt-8 bg-base-200 rounded-lg p-8">
            <h2 class="text-3xl font-bold mb-4">Start a Band at CMC</h2>
            <p class="text-lg mb-6">
                Looking for bandmates or want to showcase your existing band? Join our community and connect with other
                musicians.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/member/register" class="btn btn-primary">Join as a Member</a>
                <a href="{{ route('members.index') }}" class="btn btn-outline btn-secondary">Find Musicians</a>
                <a href="{{ route('contact') }}?topic=general" class="btn btn-outline btn-accent">Contact Us</a>
            </div>
        </div>
    </div>
</x-public.layout>
