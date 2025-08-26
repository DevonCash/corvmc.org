<x-public.layout title="{{ $memberProfile->user->name }} - Local Musician | Corvallis Music Collective">
    <div class="container mx-auto px-4 py-16">
        <x-member-profile-view :record="$memberProfile" :showEditButton="false" />
        
        <!-- Back to Directory -->
        <div class="text-center mt-8">
            <a href="{{ route('members.index') }}" class="btn btn-outline btn-primary">
                <x-unicon name="tabler:arrow-left" class="size-4 mr-2" />
                Back to Members Directory
            </a>
        </div>
    </div>
</x-public.layout>