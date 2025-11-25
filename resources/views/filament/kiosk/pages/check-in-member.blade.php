<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @if($this->selectedUserId)
            @php
                $reservations = $this->getUpcomingReservations();
                $user = \App\Models\User::find($this->selectedUserId);
            @endphp

            @if($reservations->isNotEmpty())
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold">Upcoming Reservations</h3>

                    @foreach($reservations as $reservation)
                        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="text-xl font-semibold mb-2">
                                        {{ $reservation->time_range }}
                                    </div>
                                    <div class="flex gap-4 text-sm text-gray-600 dark:text-gray-400">
                                        <span>{{ $reservation->hours_used }} hours</span>
                                        <span>•</span>
                                        <span>${{ number_format($reservation->cost->getAmount() / 100, 2) }}</span>
                                        <span>•</span>
                                        <x-filament::badge :color="$reservation->status->value === 'confirmed' ? 'success' : 'warning'">
                                            {{ $reservation->status->value }}
                                        </x-filament::badge>
                                    </div>
                                </div>

                                <x-filament::button
                                    wire:click="checkInToReservation({{ $reservation->id }})"
                                    size="xl"
                                    color="success"
                                >
                                    <x-filament::icon icon="tabler-circle-arrow-right" class="w-6 h-6 mr-2" />
                                    Check In
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-8 bg-warning-50 dark:bg-warning-950 rounded-xl border-2 border-warning-600">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="text-xl font-semibold text-warning-900 dark:text-warning-100 mb-2">
                                No Upcoming Reservations
                            </div>
                            <div class="text-warning-700 dark:text-warning-300">
                                {{ $user->name }} doesn't have any reservations today or starting within 2 hours.
                            </div>
                        </div>

                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Kiosk\Pages\WalkInReservation::getUrl(['user' => $this->selectedUserId]) }}"
                            size="xl"
                            color="primary"
                        >
                            <x-filament::icon icon="tabler-calendar-plus" class="w-6 h-6 mr-2" />
                            Create Walk-In
                        </x-filament::button>
                    </div>
                </div>
            @endif
        @endif

        <div class="mt-6">
            <x-filament::button
                type="button"
                color="gray"
                size="xl"
                tag="a"
                href="{{ \App\Filament\Kiosk\Pages\KioskDashboard::getUrl() }}"
                class="w-full sm:w-auto"
            >
                <x-filament::icon icon="heroicon-o-arrow-left" class="w-6 h-6 mr-2" />
                Back to Dashboard
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
