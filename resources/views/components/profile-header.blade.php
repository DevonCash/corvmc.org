@props(['record', 'type' => 'member'])

<div class="bg-base-200 border-2 border-base-300">
    <div class="flex flex-col lg:flex-row items-center gap-6 {{ $type === 'member' ? 'my-8 lg:m-0' : '' }}">
        {{-- Profile Photo --}}
        <div class="relative lg:border-r-2 border-base-300 {{ $type === 'band' ? 'overflow-hidden' : '' }}">
            <img src="{{ $record->avatar_url }}" 
                 alt="{{ $type === 'member' ? $record->user->name : $record->name }}" 
                 class="size-48 object-cover">
            
            @if ($record->visibility === 'private')
                <div class="absolute -top-2 -right-2">
                    <div class="bg-error text-error-content px-2 py-1 text-xs font-bold flex items-center">
                        <x-tabler-lock class="w-3 h-3 mr-1" />
                        PRIVATE
                    </div>
                </div>
            @endif
        </div>

        {{-- Profile Details --}}
        <div class="text-center lg:text-left flex-1 {{ $type === 'band' ? 'space-y-2 mb-4' : '' }}">
            <h1 class="text-4xl font-bold text-base-content {{ $type === 'member' ? 'mb-2' : '' }} tracking-tight">
                @if ($type === 'member')
                    {{ $record->user->name }}
                    @if ($record->user->pronouns)
                        <span class="font-medium text-lg text-base-content/70 mb-2">({{ $record->user->pronouns }})</span>
                    @endif
                @else
                    {{ $record->name }}
                    @if ($record->visibility === 'private')
                        <span class="bg-error text-error-content px-2 py-1 text-xs font-bold inline-flex items-center -top-2 relative">
                            <x-tabler-lock class="w-3 h-3 mr-1" />
                            PRIVATE
                        </span>
                    @elseif ($record->visibility === 'members')
                        <span class="bg-warning text-warning-content px-2 py-1 text-xs font-bold inline-flex items-center">
                            <x-tabler-users class="w-3 h-3 mr-1" />
                            MEMBERS
                        </span>
                    @endif
                @endif
            </h1>

            @if ($type === 'band')
                <div class="flex items-center justify-center lg:justify-start text-base-content/70">
                    <x-tabler-users class="w-4 h-4 mr-1" />
                    <span>{{ $record->members()->count() }}
                        {{ $record->members()->count() === 1 ? 'member' : 'members' }}</span>
                </div>
            @endif

            @if ($record->hometown)
                <div class="flex items-center justify-center lg:justify-start text-base-content/70 mb-4">
                    <x-tabler-map-pin class="w-4 h-4 mr-1" />
                    <span>{{ $record->hometown }}</span>
                </div>
            @endif

            {{-- Type-specific badges --}}
            @if ($type === 'member')
                @php $activeFlags = $record->getActiveFlagsWithLabels(); @endphp
                @if ($activeFlags && count($activeFlags) > 0)
                    <div class="flex flex-wrap gap-2 justify-center lg:justify-start">
                        @foreach ($activeFlags as $flag => $label)
                            @php
                                $flagStyle = match ($flag) {
                                    'open_to_collaboration' => 'badge-info',
                                    'available_for_hire' => 'badge-success',
                                    'looking_for_band' => 'badge-secondary',
                                    'music_teacher' => 'badge-warning',
                                    'sponsor' => 'badge-accent',
                                    'sustaining_member' => 'badge-primary',
                                    default => 'badge-neutral',
                                };
                            @endphp
                            <span class="badge {{ $flagStyle }} badge-sm font-bold uppercase tracking-wide">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                @endif
            @else
                {{-- Band Genres --}}
                @php $genres = $record->tagsWithType('genre')->pluck('name'); @endphp
                @if ($genres->count() > 0)
                    <div class="flex flex-wrap gap-2 justify-center lg:justify-start">
                        @foreach ($genres as $genre)
                            <span class="badge badge-secondary badge-sm font-bold uppercase tracking-wide">
                                {{ $genre }}
                            </span>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>