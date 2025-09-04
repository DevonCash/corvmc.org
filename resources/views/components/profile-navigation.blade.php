@props(['record', 'type' => 'member', 'canEdit' => false])

<div class='navbar flex-col sm:flex-row gap-4 mt-2'>
    <div class='navbar-start justify-center sm:justify-start'>
        @php 
            $backRoutes = match($type) {
                'band' => [
                    'filament' => route('filament.member.resources.bands.index'),
                    'public' => route('bands.index')
                ],
                'member' => [
                    'filament' => route('filament.member.resources.directory.index'), 
                    'public' => route('members.index')
                ]
            };
            $route = request()->routeIs('filament.*') ? $backRoutes['filament'] : $backRoutes['public'];
        @endphp
        <a href="{{ $route }}" class="btn btn-sm btn-outline flex items-center">
            <x-tabler-arrow-left class="w-4 h-4 mr-1" />
            Back to Directory
        </a>
    </div>

    <div class="navbar-center text-center">
        <div class="inline-block">
            <div class="text-xs uppercase tracking-widest text-base-content/60 mb-1">
                @if ($type === 'member')
                    @if ($record->hasFlag('sponsor'))
                        Sponsor
                    @elseif($record->hasFlag('sustaining_member'))
                        Sustaining Member
                    @else
                        Member
                    @endif
                    since {{ $record->created_at->format('Y') }}
                @else
                    Member since {{ $record->created_at->format('Y') }}
                @endif
            </div>
            <div class="w-16 h-0.5 bg-primary mx-auto"></div>
        </div>
    </div>

    <div class="navbar-end flex items-center justify-center sm:justify-end">
        @if ($canEdit)
            @php
                $editRoutes = match($type) {
                    'band' => route('filament.member.resources.bands.edit', ['record' => $record]),
                    'member' => route('filament.member.resources.directory.edit', ['record' => $record->id])
                };
            @endphp
            <a href="{{ $editRoutes }}" class="btn btn-primary btn-sm uppercase tracking-wide">
                <x-tabler-edit class="w-4 h-4 mr-2" />
                Edit {{ ucfirst($type) }}
            </a>
        @endif
    </div>
</div>