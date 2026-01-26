<x-public.layout title="{{ $memberProfile->user->name }} - Local Musician | Corvallis Music Collective">
    <x-membership::member-profile-view :record="$memberProfile" :showEditButton="false" />
</x-public.layout>
