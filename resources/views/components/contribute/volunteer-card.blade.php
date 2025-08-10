<div class="card bg-primary text-primary-content shadow-xl">
    <div class="card-body">
        <div class="flex items-center gap-4 mb-4">
            <x-unicon name="tabler:heart-handshake" class="size-12" />
            <h2 class="card-title text-3xl">Volunteer Your Time</h2>
        </div>
        <p class="text-lg mb-6">
            Join our team of volunteers and make a direct impact on our community. From event support to
            facility maintenance, there are opportunities for everyone.
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
            <a href="{{ route('volunteer') }}" class="btn btn-secondary btn-lg flex-1">Learn More About
                Volunteering</a>
        </div>
    </div>
</div>