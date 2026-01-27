<x-filament-panels::page>
    <div class="space-y-6 max-w-3xl mx-auto">
        {{-- Hero Section --}}
        <div class="text-center">
            <img
                src="{{ $band->avatar_thumb_url }}"
                alt="{{ $band->name }}"
                class="w-32 h-32 rounded-xl object-cover mx-auto ring-4 ring-white dark:ring-gray-800 shadow-lg"
            />
            <h1 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">{{ $band->name }}</h1>
            @if($band->hometown)
                <p class="text-gray-500 dark:text-gray-400">{{ $band->hometown }}</p>
            @endif

            @if($band->genres->count() > 0)
                <div class="mt-3 flex gap-2 flex-wrap justify-center">
                    @foreach($band->genres->take(5) as $genre)
                        <x-filament::badge>{{ $genre->name }}</x-filament::badge>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Band Stats --}}
        @php $stats = $this->getBandStats(); @endphp
        <div class="grid grid-cols-2 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['active_members'] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Active Members</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['visibility'] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Profile Visibility</div>
                </div>
            </x-filament::section>
        </div>

        {{-- Bio Section --}}
        @if($band->sanitized_bio)
            <x-filament::section>
                <x-slot name="heading">About the Band</x-slot>
                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {!! $band->sanitized_bio !!}
                </div>
            </x-filament::section>
        @endif

        {{-- Invitation Details --}}
        <x-filament::section>
            <x-slot name="heading">Your Invitation</x-slot>

            <div class="space-y-4">
                {{-- Role with Icon --}}
                <div class="flex items-center gap-3">
                    @if($membership->role === 'admin')
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-success-50 dark:bg-success-500/10 flex items-center justify-center">
                            <x-tabler-shield class="w-5 h-5 text-success-600 dark:text-success-400" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-white">Admin Role</span>
                                <x-filament::badge color="success">Admin</x-filament::badge>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Full management access to band settings and members</p>
                        </div>
                    @else
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-info-50 dark:bg-info-500/10 flex items-center justify-center">
                            <x-tabler-users-group class="w-5 h-5 text-info-600 dark:text-info-400" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-white">Member Role</span>
                                <x-filament::badge color="info">Member</x-filament::badge>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Standard member with booking privileges</p>
                        </div>
                    @endif
                </div>

                {{-- Position --}}
                @if($membership->position)
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <x-tabler-music class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        </div>
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">Position</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $membership->position }}</p>
                        </div>
                    </div>
                @endif

                {{-- Invited Timestamp --}}
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <x-tabler-clock class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div>
                        <span class="font-medium text-gray-900 dark:text-white">Invited</span>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $membership->invited_at->diffForHumans() }}</p>
                    </div>
                </div>

                {{-- Capabilities --}}
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">As a {{ $membership->role }}, you'll be able to:</h4>
                    <ul class="space-y-1">
                        @foreach($this->getRoleCapabilities() as $capability)
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <x-tabler-check class="w-4 h-4 text-success-500 flex-shrink-0" />
                                {{ $capability }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </x-filament::section>

        {{-- Actions --}}
        <div class="flex gap-4 justify-center">
            {{ $this->declineAction }}
            {{ $this->acceptAction }}
        </div>
    </div>
</x-filament-panels::page>
