@props(['item'])

@php
    $member = $item;
@endphp

<div class="card bg-base-100 shadow-lg hover:shadow-xl transition-shadow">
    <figure class="px-6 pt-6">
        <div class="avatar">
            <div class="w-24 rounded-full">
                <img src="{{ $member->avatar_thumb_url }}" alt="{{ $member->user->name }}" />
            </div>
        </div>
    </figure>

    <div class="card-body items-center text-center">
        <h2 class="card-title">{{ $member->user->name }}</h2>

        @if ($member->hometown)
            <p class="text-sm opacity-70">
                <x-unicon name="tabler:map-pin" class="size-4 inline mr-1" />
                {{ $member->hometown }}
            </p>
        @endif

        @if ($member->skills)
            <div class="flex flex-wrap gap-1 justify-center mt-2">
                @foreach (array_slice($member->skills, 0, 3) as $skill)
                    <span class="badge badge-primary badge-sm">{{ $skill }}</span>
                @endforeach
                @if (count($member->skills) > 3)
                    <span class="badge badge-outline badge-sm">+{{ count($member->skills) - 3 }}</span>
                @endif
            </div>
        @endif

        @if ($member->bio)
            <p class="text-sm mt-2">{{ Str::limit(strip_tags($member->bio), 80) }}</p>
        @endif

        <div class="card-actions justify-end mt-4">
            <a href="{{ route('members.show', $member) }}" class="btn btn-primary btn-sm">
                View Profile
            </a>
        </div>
    </div>
</div>