@props(['item'])

@php
    $member = $item;
@endphp

<a href="{{ route('members.show', $member) }}" class="block group">
    <div
        class="relative rounded-xl hover:shadow-lg transition-all duration-300 group-hover:scale-[1.02] aspect-[16/10] min-h-[200px] corvmc-header-stripes p-1">
        <div class="relative rounded-lg overflow-hidden h-full w-full flex flex-col bg-base-200">
            <!-- TOP ROW: Logo + Flags -->
            <div class="flex items-center justify-between px-2 py-1 bg-base-300">
                <x-logo :soundLines="false" class="h-5" />
                <div class="flex gap-1">
                    @if ($member->hasFlag('music_teacher'))
                        <div class="badge badge-secondary badge-xs">Teacher</div>
                    @endif
                    @if ($member->hasFlag('open_to_collaboration'))
                        <div class="badge badge-info badge-xs">Open to Collab</div>
                    @endif
                    @if ($member->hasFlag('available_for_hire'))
                        <div class="badge badge-success badge-xs">For Hire</div>
                    @endif
                    @if ($member->hasFlag('looking_for_band'))
                        <div class="badge badge-warning badge-xs">Seeking Band</div>
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
                    <div class="flex-shrink-0 p-1 h-6">
                        @if ($member->hometown)
                            <p class="text-xs text-base-content/70 flex items-center gap-1">
                                <x-unicon name="tabler:map-pin" class="size-3" />
                                <span class="truncate">{{ $member->hometown }}</span>
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Skills and Pronouns -->
                <div class=" min-w-0 space-y-1 flex-2 flex flex-col p-2 h-full">
                    <!-- Pronouns -->
                    <div>
                        <h3 class="font-bold text-base leading-tight">{{ $member->user->name }}</h3>
                        @if ($member->pronouns)
                            <p class="text-xs text-base-content/60">{{ $member->pronouns }}
                            </p>
                        @endif

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
                <x-unicon name="tabler:external-link" class="size-4 text-base-content/40" />
            </div>
        </div>
    </div>
</a>
