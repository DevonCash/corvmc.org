<?php

namespace App\Filament\Resources\ActivityLog\Tables;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms;
use Filament\Actions;

class ActivityLogTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Activity')
                    ->formatStateUsing(function (string $state, Activity $record): string {
                        // Use the same formatting logic as the widget
                        return self::formatActivityDescription($record);
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject Type')
                    ->formatStateUsing(
                        fn(?string $state): string =>
                        $state ? class_basename($state) : 'System'
                    )
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'App\\Models\\User' => 'info',
                        'App\\Models\\MemberProfile' => 'success',
                        'App\\Models\\Band' => 'warning',
                        'App\\Models\\Production' => 'primary',
                        'App\\Models\\Reservation' => 'secondary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->description(
                        fn(Activity $record): string =>
                        $record->created_at->format('M j, Y g:i A')
                    ),

                Tables\Columns\IconColumn::make('icon')
                    ->label('')
                    ->icon(function (Activity $record): string {
                        return self::getActivityIcon($record);
                    })
                    ->color(function (Activity $record): string {
                        return self::getActivityColor($record);
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Content Type')
                    ->options([
                        'App\\Models\\User' => 'Users',
                        'App\\Models\\MemberProfile' => 'Member Profiles',
                        'App\\Models\\Band' => 'Bands',
                        'App\\Models\\Production' => 'Events',
                        'App\\Models\\Reservation' => 'Reservations',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(
                        User::pluck('name', 'id')->toArray()
                    )
                    ->searchable(),

                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->schema([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    }),
            ])
            ->recordActions([
                Actions\Action::make('view_subject')
                    ->label('View Subject')
                    ->icon('tabler-eye')
                    ->url(function (Activity $record): ?string {
                        if (!$record->subject) {
                            return null;
                        }

                        return match ($record->subject_type) {
                            'App\\Models\\MemberProfile' => route('filament.member.resources.directory.view', $record->subject),
                            'App\\Models\\Band' => route('filament.member.resources.bands.view', $record->subject),
                            'App\\Models\\Production' => route('filament.member.resources.productions.edit', $record->subject),
                            'App\\Models\\Reservation' => route('filament.member.resources.reservations.view', $record->subject),
                            default => null,
                        };
                    })
                    ->openUrlInNewTab()
                    ->visible(fn(Activity $record): bool => $record->subject !== null),

                Actions\DeleteAction::make()
                    ->visible(fn(): bool => User::me()?->can('delete activity log') ?? false),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn(): bool => User::me()?->can('delete activity log') ?? false),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected static function formatActivityDescription(Activity $activity): string
    {
        $causerName = $activity->causer?->name ?? 'System';

        return match ($activity->description) {
            'User account created' => "{$causerName} joined the community",
            'User account updated' => "{$causerName} updated their account",
            'Production created' => self::formatProductionDescription($activity, $causerName, 'created'),
            'Production updated' => self::formatProductionDescription($activity, $causerName, 'updated'),
            'Production deleted' => "{$causerName} removed an event",
            'Band profile created' => self::formatBandDescription($activity, $causerName, 'created'),
            'Band profile updated' => self::formatBandDescription($activity, $causerName, 'updated'),
            'Band profile deleted' => "{$causerName} removed a band profile",
            'Member profile created' => "{$causerName} completed their profile",
            'Member profile updated' => "{$causerName} updated their profile",
            'Practice space reservation created' => self::formatReservationDescription($activity, $causerName, 'booked'),
            'Practice space reservation updated' => self::formatReservationDescription($activity, $causerName, 'updated'),
            'Practice space reservation deleted' => self::formatReservationDescription($activity, $causerName, 'cancelled'),
            default => $activity->description ?: "{$causerName} performed an action",
        };
    }

    protected static function formatProductionDescription(Activity $activity, string $causerName, string $action): string
    {
        $production = $activity->subject;
        if ($production && $production->title) {
            return "{$causerName} {$action} event \"{$production->title}\"";
        }
        return "{$causerName} {$action} an event";
    }

    protected static function formatBandDescription(Activity $activity, string $causerName, string $action): string
    {
        $band = $activity->subject;
        if ($band && $band->name) {
            $actionText = $action === 'created' ? 'created' : 'updated';
            return "{$causerName} {$actionText} band \"{$band->name}\"";
        }
        return "{$causerName} {$action} a band profile";
    }

    protected static function formatReservationDescription(Activity $activity, string $causerName, string $action): string
    {
        $currentUser = User::me();
        $reservation = $activity->subject;

        // Only show details for own reservations or if user has permission
        if (
            $currentUser && $reservation &&
            ($reservation->user_id === $currentUser->id || $currentUser->can('view reservations'))
        ) {

            $actionText = match ($action) {
                'booked' => 'booked the practice space',
                'updated' => 'updated their reservation',
                'cancelled' => 'cancelled a reservation',
                default => "{$action} a reservation",
            };

            return "{$causerName} {$actionText}";
        }

        // Generic message for others
        return "Practice space activity";
    }

    protected static function getActivityIcon(Activity $activity): string
    {
        return match ($activity->description) {
            'User account created' => 'tabler-user-plus',
            'User account updated' => 'tabler-user-edit',
            'Production created' => 'tabler-calendar-plus',
            'Production updated' => 'tabler-calendar',
            'Production deleted' => 'tabler-calendar-minus',
            'Band profile created' => 'tabler-users-plus',
            'Band profile updated' => 'tabler-users',
            'Band profile deleted' => 'tabler-users-minus',
            'Member profile created' => 'tabler-user-check',
            'Member profile updated' => 'tabler-user-edit',
            'Practice space reservation created' => 'tabler-home-plus',
            'Practice space reservation updated' => 'tabler-home-edit',
            'Practice space reservation deleted' => 'tabler-home-minus',
            default => 'tabler-activity',
        };
    }

    protected static function getActivityColor(Activity $activity): string
    {
        return match (true) {
            str_contains($activity->description, 'created') => 'success',
            str_contains($activity->description, 'updated') => 'info',
            str_contains($activity->description, 'deleted') => 'danger',
            default => 'gray',
        };
    }
}
