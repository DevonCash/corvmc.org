<x-public.layout title="{{ $band->name }} - Local Band | Corvallis Music Collective">
    <x-band-profile-view :record="$band" :show-edit-button="false" />
</x-public.layout>
