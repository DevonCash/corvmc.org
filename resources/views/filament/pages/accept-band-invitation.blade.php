<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Band Information Card --}}
        <x-filament::section>
            <x-slot name="heading">
                Band Details
            </x-slot>

            <div class="flex items-start gap-4">
                <img
                    src="{{ $band->avatar_thumb_url }}"
                    alt="{{ $band->name }}"
                    class="w-24 h-24 rounded-lg object-cover"
                />

                <div class="flex-1">
                    <h2 class="text-xl font-bold">{{ $band->name }}</h2>
                    @if($band->hometown)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $band->hometown }}</p>
                    @endif

                    @if($band->genres->count() > 0)
                        <div class="mt-2 flex gap-2 flex-wrap">
                            @foreach($band->genres->take(5) as $genre)
                                <x-filament::badge>{{ $genre->name }}</x-filament::badge>
                            @endforeach
                        </div>
                    @endif

                    @if($band->sanitized_bio)
                        <div class="mt-4 prose prose-sm dark:prose-invert max-w-none">
                            {!! $band->sanitized_bio !!}
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Invitation Details --}}
        <x-filament::section>
            <x-slot name="heading">
                Your Invitation
            </x-slot>

            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Role</dt>
                    <dd class="mt-1">
                        <x-filament::badge color="{{ $membership->role === 'admin' ? 'success' : 'info' }}">
                            {{ ucfirst($membership->role) }}
                        </x-filament::badge>
                    </dd>
                </div>

                @if($membership->position)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Position</dt>
                        <dd class="mt-1 text-sm">{{ $membership->position }}</dd>
                    </div>
                @endif

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Invited</dt>
                    <dd class="mt-1 text-sm">{{ $membership->invited_at->diffForHumans() }}</dd>
                </div>
            </dl>
        </x-filament::section>

        {{-- Actions --}}
        <div class="flex gap-4 justify-end">
            {{ $this->declineAction }}
            {{ $this->acceptAction }}
        </div>
    </div>
</x-filament-panels::page>
