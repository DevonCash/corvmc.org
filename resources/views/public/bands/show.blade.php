<x-public.layout title="{{ $bandProfile->name }} - Local Band | Corvallis Music Collective">
    <x-band-profile-view :record="$bandProfile" :show-edit-button="false" />
</x-public.layout>
