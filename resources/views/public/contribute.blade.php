<x-public.layout title="Contribute to CMC - Support Local Music | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-success/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Help Support Our Mission</h1>
                <p class="py-6 text-lg">
                    There are many ways to contribute to the Corvallis Music Collective and help us build a stronger
                    music community
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 pt-16">
        <!-- Main contribution options -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
            <x-contribute.volunteer-card />
            <x-contribute.donation-card />
        </div>

        <x-contribute.other-ways />

        <x-contribute.in-kind-donations />


        <x-contribute.wishlist-section />

        <x-contribute.wishlist-cta />
    </div>
</x-public.layout>
