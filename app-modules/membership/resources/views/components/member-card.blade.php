@props(['member', 'item' => null, 'link' => null])

@php
    $member = $member ?? $item;
    $link ??= route('members.show', $member);
@endphp

@if ($link)
    <a href="{{ $link }}" class="block group min-h-[200px] max-w-[400px]">
    @else
        <div class="block group min-h-[200px] max-w-[400px]">
@endif
<div
    class="relative rounded-xl hover:shadow-lg transition-all duration-300 group-hover:scale-[1.02] aspect-[16/10]  corvmc-header-stripes p-1">
    <div class="relative rounded-lg overflow-hidden h-full w-full flex flex-col bg-base-200">
        <!-- TOP ROW: Logo + Flags -->
        <div class="flex items-stretch justify-between bg-base-300">
            <div class="flex items-center px-2 py-1">
                <x-logo :soundLines="false" class="h-5" />
            </div>
            <div class="flex items-stretch">
                @if ($member->hasFlag('music_teacher'))
                    <div class="bg-secondary text-secondary-content text-xs font-medium px-2 py-1 flex items-center">Teacher</div>
                @endif
                @if ($member->hasFlag('open_to_collaboration'))
                    <div class="bg-info text-info-content text-xs font-medium px-2 py-1 flex items-center">Open to Collab</div>
                @endif
                @if ($member->hasFlag('available_for_hire'))
                    <div class="bg-success text-success-content text-xs font-medium px-2 py-1 flex items-center">For Hire</div>
                @endif
                @if ($member->hasFlag('looking_for_band'))
                    <div class="bg-warning text-warning-content text-xs font-medium px-2 py-1 flex items-center">Seeking Band</div>
                @endif
            </div>
        </div>

        <!-- CENTER ROW: Identity -->
        <div class="flex grow">
            <!-- Name and Location Line -->


            <!-- Member Photo -->
            <div class="flex-1 flex flex-col">
                <div class='flex-1 rounded-br-md overflow-hidden min-h-0'>
                    <img src="{{ $member->avatar_url }}" alt="{{ $member->user->name }}"
                        class="w-full h-full object-cover" />
                </div>
            </div>

            <!-- Skills and Pronouns -->
            <div class=" min-w-0 space-y-1 flex-2 flex flex-col p-2 h-full">
                <!-- Pronouns and Hometown -->
                <div>
                    <h3 class="font-bold text-base leading-tight">{{ $member->user->name }}</h3>
                    <div class="flex items-center justify-between gap-2">
                        @if ($member->user->pronouns)
                            <p class="text-xs text-base-content/60">{{ $member->user->pronouns }}</p>
                        @endif
                        @if ($member->hometown)
                            <p class="text-xs text-base-content/70 flex items-center gap-1">
                                <x-icon name="tabler-map-pin" class="size-3" />
                                <span class="truncate">{{ $member->hometown }}</span>
                            </p>
                        @endif
                    </div>
                </div>
                <!-- Skills as Columns -->
                <div class="text-xs text-base-content/80 grow">
                    @if ($member->skills && count($member->skills) > 0)
                        <div class="p-1 mt-1 border-t border-base-content/10">
                            <div class="columns-2 gap-2">
                                @foreach ($member->skills as $skill)
                                    <div class="break-inside-avoid mb-1">{{ $skill }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

            </div>
        </div>

        <!-- BOTTOM ROW: Membership Type + Year -->
        <div class="flex justify-between items-center text-xs text-base-content/50 bg-base-300 p-1 px-2">
            <span class="font-medium">
                @if ($member->hasFlag('sponsor'))
                    Sponsor
                @elseif ($member->hasFlag('sustaining_member'))
                    Sustaining Member
                @else
                    Member
                @endif
            </span>
            <span>Since {{ $member->created_at->format('Y') }}</span>
        </div>

        <!-- Hover Effect Indicator -->
        <div class="absolute top-9 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            <x-icon name="tabler-external-link" class="size-4 text-base-content/40" />
        </div>
    </div>
</div>
@if ($link)
    </a>
@else
    </div>
@endif
