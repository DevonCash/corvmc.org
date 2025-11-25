<?php

namespace App\Filament\Kiosk\Pages;

use App\Actions\CheckIns\CheckInUser;
use App\Models\Reservation;
use App\Models\User;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CheckInMember extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected string $view = 'filament.kiosk.pages.check-in-member';

    protected static ?string $title = 'Check In for Reservation';

    protected static ?string $navigationLabel = 'Check In';

    protected static ?int $navigationSort = 0;

    public ?array $data = [];

    public ?int $selectedUserId = null;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Search for Member')
                    ->searchable()
                    ->autofocus()
                    ->getSearchResultsUsing(
                        fn(string $search): array =>
                        User::where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($user) => [$user->id => "{$user->name} ({$user->email})"])
                            ->toArray()
                    )
                    ->getOptionLabelUsing(
                        fn($value): ?string =>
                        User::find($value)?->name
                    )
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->selectedUserId = $state;
                    }),
            ])
            ->statePath('data');
    }

    public function getUpcomingReservations()
    {
        if (!$this->selectedUserId) {
            return collect();
        }

        return Reservation::where('reservable_type', User::class)
            ->where('reservable_id', $this->selectedUserId)
            ->where(function ($query) {
                $query->whereDate('reserved_at', today())
                    ->orWhere(function ($q) {
                        $q->where('reserved_at', '>=', now())
                            ->where('reserved_at', '<=', now()->addHours(2));
                    });
            })
            ->where('status', '!=', 'cancelled')
            ->orderBy('reserved_at')
            ->get();
    }

    public function checkInToReservation(int $reservationId): void
    {
        try {
            $reservation = Reservation::findOrFail($reservationId);
            $user = User::findOrFail($this->selectedUserId);

            CheckInUser::run($user, $reservation);

            Notification::make()
                ->success()
                ->title('Checked In')
                ->body("{$user->name} has been checked in for their reservation.")
                ->send();

            // Reset
            $this->selectedUserId = null;
            $this->data = [];
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Check-In Failed')
                ->body($e->getMessage())
                ->send();
        }
    }
}
