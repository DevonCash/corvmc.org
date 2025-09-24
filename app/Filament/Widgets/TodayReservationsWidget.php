<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TodayReservationsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $canViewAll = $user?->can('view reservations');

        return $table
            ->query(
                Reservation::with('user')
                    ->whereDate('reserved_at', Carbon::today())
                    ->where('status', '!=', 'cancelled')
                    ->when(! $canViewAll, function ($query) use ($user) {
                        return $query->where('user_id', $user->id);
                    })
                    ->orderBy('reserved_at')
            )
            ->heading('Today\'s Practice Space Schedule')
            ->description('Reservations for ' . Carbon::today()->format('l, F j, Y'))
            ->emptyStateHeading('No reservations today')
            ->emptyStateDescription('The practice space is available all day!')
            ->emptyStateIcon('tabler-calendar-smile')
            ->columns([
                TextColumn::make('reserved_at')
                    ->label('Time')
                    ->formatStateUsing(function ($state, Reservation $record) {
                        return $record->reserved_at->format('g:i A') . ' - ' . $record->reserved_until->format('g:i A');
                    })
                    ->badge()
                    ->color(function (Reservation $record) {
                        if ($record->reserved_at->isPast() && $record->reserved_until->isFuture()) {
                            return 'success'; // Currently in session
                        }
                        if ($record->reserved_at->isFuture()) {
                            return 'info'; // Upcoming
                        }

                        return 'gray'; // Past
                    }),

                TextColumn::make('user.name')
                    ->label('Reserved by')
                    ->formatStateUsing(function ($state, Reservation $record) use ($user, $canViewAll) {
                        if ($canViewAll || $record->user_id === $user?->id) {
                            return $record->user->name;
                        }

                        return 'Reserved';
                    })
                    ->icon(function (Reservation $record) use ($user) {
                        return $record->user_id === $user?->id ? 'tabler-user' : 'tabler-users';
                    })
                    ->iconColor(function (Reservation $record) use ($user) {
                        return $record->user_id === $user?->id ? 'success' : 'gray';
                    }),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->formatStateUsing(function ($state, Reservation $record) {
                        $hours = $record->reserved_at->diffInMinutes($record->reserved_until) / 60;

                        return number_format($hours, 1) . ' hrs';
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'confirmed',
                        'warning' => 'pending',
                        'danger' => 'cancelled',
                    ]),

                TextColumn::make('notes')
                    ->label('Activity')
                    ->formatStateUsing(function ($state, Reservation $record) use ($user, $canViewAll) {
                        if ($canViewAll || $record->user_id === $user?->id) {
                            return $record->notes ?: 'Practice session';
                        }
                        return 'Private session';
                    })
                    ->limit(30)
                    ->tooltip(function ($state, Reservation $record) use ($user, $canViewAll) {
                        if (($canViewAll || $record->user_id === $user?->id) && $record->notes) {
                            return $record->notes;
                        }
                        return null;
                    }),
            ])
            ->recordUrl(fn(Model $record) => ReservationResource::getUrl('view', ['record' => $record]))
            ->defaultSort('reserved_at')
            ->paginated(false);
    }

    public function getDisplayName(): string
    {
        return 'Today\'s Schedule';
    }

    protected function getTableHeading(): string
    {
        return 'Today\'s Practice Space Schedule';
    }
}
