<x-public.layout title="Directory | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-80 bg-secondary/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Directory</h1>
                <p class="py-6 text-lg">
                    Discover the talented musicians and bands that make up our community
                </p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="container mx-auto px-4 py-8">
        <div x-data="{ tab: '{{ request('tab', 'musicians') }}' }" x-init="
            // Update URL when tab changes
            $watch('tab', value => {
                const url = new URL(window.location);
                url.searchParams.set('tab', value);
                window.history.replaceState({}, '', url);
            })
        ">
            <!-- Tab Buttons -->
            <div class="flex justify-center mb-8">
                <div class="inline-flex rounded-lg bg-base-200 p-1 gap-1">
                    <button
                        class="btn gap-2"
                        :class="tab === 'musicians' ? 'btn-primary' : 'btn-ghost'"
                        @click="tab = 'musicians'"
                    >
                        <x-tabler-users class="w-5 h-5" />
                        Musicians
                    </button>
                    <button
                        class="btn gap-2"
                        :class="tab === 'bands' ? 'btn-primary' : 'btn-ghost'"
                        @click="tab = 'bands'"
                    >
                        <x-tabler-microphone-2 class="w-5 h-5" />
                        Bands
                    </button>
                </div>
            </div>

            <!-- Tab Content -->
            <div x-show="tab === 'musicians'" x-cloak>
                @livewire('members-grid')
            </div>

            <div x-show="tab === 'bands'" x-cloak>
                @livewire('bands-grid')
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="container mx-auto px-4">
        <div class="text-center mt-8 bg-base-200 rounded-lg p-8">
            <h2 class="text-3xl font-bold mb-4">Join Our Community</h2>
            <p class="text-lg mb-6">
                Are you a musician in the Corvallis area? Join our directory, connect with other artists, and showcase your band.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/member/register" class="btn btn-primary btn-lg">Become a Member</a>
                <a href="{{ route('contact') }}?topic=general" class="btn btn-outline btn-lg">Contact Us</a>
            </div>
        </div>
    </div>
</x-public.layout>
