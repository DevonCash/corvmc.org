@props(['record', 'showEditButton' => false])

{{-- Concert Program Layout - mimics a folded program booklet --}}
<div class="max-w-5xl mx-auto relative">
    <x-profile-navigation :record="$record" type="band" :canEdit="auth()->user()?->can('update', $record)" />
    <x-profile-header :record="$record" type="band" />

    {{-- Program Content - Two Column Layout like program booklet --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 bg-base-100 border-x-2 border-base-300">

        {{-- Main Band Details --}}
        <div class="lg:col-span-2 px-8 py-6 space-y-8">

            {{-- Program Notes / Bio --}}
            @if ($record->bio)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        About the Band
                    </h2>
                    <div class="prose max-w-none text-base-content/80 leading-relaxed">
                        {!! $record->bio !!}
                    </div>
                </section>
            @endif

            {{-- Musical Influences --}}
            @php $influences = $record->tagsWithType('influence')->pluck('name'); @endphp
            @if ($influences->count() > 0)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-4 uppercase tracking-wide border-b border-base-300 pb-2">
                        Influences
                    </h2>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($influences as $influence)
                            @if ($showEditButton)
                                <a href="{{ route('filament.member.resources.bands.index', ['tableFilters' => ['influences' => ['values' => [$influence]]]]) }}"
                                    target="_blank" class="badge badge-accent badge-sm hover:badge-accent/80">
                                    {{ $influence }}
                                </a>
                            @else
                                <span class="badge badge-accent badge-sm">{{ $influence }}</span>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Band Members --}}
            @if ($record->activeMembers()->count() > 0)
                <section>
                    <h2
                        class="text-lg font-bold text-base-content mb-6 uppercase tracking-wide border-b border-base-300 pb-2">
                        Members
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($record->activeMembers as $memberRecord)
                            @php
                                $displayName = $memberRecord->display_name;
                                // Check if member has a CMC account and profile
                                $hasProfile = false;
                                if ($memberRecord->user && $memberRecord->user->profile) {
                                    try {
                                        $hasProfile = $memberRecord->user->profile->isVisible(auth()->user());
                                    } catch (Exception $e) {
                                        // If isVisible method doesn't exist or fails, check basic visibility
        $hasProfile =
            $memberRecord->user->profile->visibility === 'public' ||
            ($memberRecord->user->profile->visibility === 'members' && auth()->check());
                                    }
                                }
                            @endphp
                            <div class="flex items-center justify-between p-3 bg-base-200 rounded-lg">
                                <div class="min-w-0 flex-1">
                                    @if ($hasProfile)
                                        <a href="{{ $showEditButton ? route('filament.member.resources.directory.view', ['record' => $memberRecord->user->profile->id]) : route('members.show', $memberRecord->user->profile) }}"
                                            class="font-medium text-primary hover:text-primary-focus truncate block">
                                            {{ $displayName }}
                                        </a>
                                    @elseif($memberRecord->is_cmc_member)
                                        <span class="font-medium text-base-content truncate block"
                                            title="CMC Member (profile not visible)">{{ $displayName }}</span>
                                    @else
                                        <span
                                            class="font-medium text-base-content truncate block">{{ $displayName }}</span>
                                    @endif
                                    @if ($memberRecord->position)
                                        <p class="text-xs text-base-content/60 truncate">{{ $memberRecord->position }}
                                        </p>
                                    @endif
                                </div>
                                @if ($memberRecord->is_cmc_member)
                                    <div class="flex-shrink-0 m-2 opacity-30 ">
                                        <x-logo class="size-6" :soundLines="false" />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <x-profile-recordings :embeds="$record->embeds" :canEdit="$showEditButton && auth()->check() && auth()->user()->can('update', $record)" :editRoute="route('filament.member.resources.bands.edit', ['record' => $record])" type="band" />
        </div>

        {{-- Program Sidebar - Band Info & Credits --}}
        <div class="bg-base-200 px-6 py-6 space-y-6 border-l border-base-300">

            <x-profile-contact :profile="$record" />

            {{-- Band Links --}}
            @if ($record->links && count($record->links) > 0)
                <div>
                    <h3
                        class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
                        More Information
                    </h3>
                    <x-social-links :links="$record->links" />
                </div>
            @endif

        </div>
    </div>
</div>
