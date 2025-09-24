<x-public.layout title="Musicians Directory | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-secondary/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Our Musicians</h1>
                <p class="py-6 text-lg">
                    Meet the talented artists who make up our vibrant music community
                </p>
            </div>
        </div>
    </div>

    @livewire('members-grid')

    <!-- Call to Action -->
    <div class="container mx-auto px-4">
        <div class="text-center mt-8 bg-base-200 rounded-lg p-8">
            <h2 class="text-3xl font-bold mb-4">Join Our Community</h2>
            <p class="text-lg mb-6">
                Are you a musician in the Corvallis area? Join our directory and connect with other local artists.
            </p>
            <a href="/member/register" class="btn btn-primary btn-lg">Become a Member</a>
        </div>
    </div>
</x-public.layout>
