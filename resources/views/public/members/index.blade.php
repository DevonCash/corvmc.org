<x-public.layout title="Musician Members Directory | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-gradient-to-r from-primary/10 to-secondary/10">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Our Musicians</h1>
                <p class="py-6 text-lg">
                    Meet the talented artists who make up our vibrant music community
                </p>
            </div>
        </div>
    </div>

    <x-searchable-grid 
        title="Find Musicians"
        search-placeholder="Search by name or instrument"
        search-name="filter[name]"
        :items="$members"
        :total-count="$members->total()"
        empty-icon="tabler:music"
        empty-title="No musicians found"
        empty-message="Try adjusting your search criteria or check back later."
        card-component="member-card"
        :filters="[
            [
                'name' => 'filter[withAllTags]',
                'label' => 'Genre',
                'placeholder' => 'All Genres',
                'options' => \Spatie\Tags\Tag::getWithType('genre')->pluck('name', 'name')->toArray()
            ],
            [
                'name' => 'filter[withAllTags]',
                'label' => 'Skills', 
                'placeholder' => 'All Skills',
                'options' => \Spatie\Tags\Tag::getWithType('skill')->pluck('name', 'name')->toArray()
            ],
            [
                'name' => 'filter[hometown]',
                'label' => 'Location',
                'placeholder' => 'All Locations',
                'options' => \App\Models\MemberProfile::select('hometown')->distinct()->get()->pluck('hometown')->filter(fn($location) => $location)->mapWithKeys(fn($location) => [$location => $location])->toArray()
            ]
        ]"
    />

    <!-- Call to Action -->
    <div class="container mx-auto px-4">
        <div class="text-center mt-16 bg-base-200 rounded-lg p-8">
            <h2 class="text-3xl font-bold mb-4">Join Our Community</h2>
            <p class="text-lg mb-6">
                Are you a musician in the Corvallis area? Join our directory and connect with other local artists.
            </p>
            <a href="/member/register" class="btn btn-primary btn-lg">Become a Member</a>
        </div>
    </div>
</x-public.layout>
