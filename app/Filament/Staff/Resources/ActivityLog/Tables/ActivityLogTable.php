<?php

namespace App\Filament\Staff\Resources\ActivityLog\Tables;

use App\Models\User;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

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
                        fn (?string $state): string => $state ? str($state)->replace('_', ' ')->title()->toString() : 'System'
                    )
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'user' => 'info',
                        'member_profile' => 'success',
                        'band' => 'warning',
                        'event' => 'primary',
                        'reservation', 'rehearsal_reservation' => 'secondary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'confirmed' => 'success',
                        'cancelled', 'auto_cancelled' => 'danger',
                        'rescheduled' => 'warning',
                        'payment_recorded' => 'success',
                        'comped' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->description(
                        fn (Activity $record): string => $record->created_at->format('M j, Y g:i A')
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
                        'user' => 'Users',
                        'member_profile' => 'Member Profiles',
                        'band' => 'Bands',
                        'event' => 'Events',
                        'reservation' => 'Reservations',
                        'rehearsal_reservation' => 'Rehearsal Reservations',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'rescheduled' => 'Rescheduled',
                        'payment_recorded' => 'Payment Recorded',
                        'comped' => 'Comped',
                        'auto_cancelled' => 'Auto-Cancelled',
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
                            ->when($data['created_from'], fn ($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn ($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    }),
            ])
            ->recordActions([
                Actions\Action::make('view_subject')
                    ->label('View Subject')
                    ->icon('tabler-eye')
                    ->url(fn (Activity $record): ?string => self::getSubjectUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Activity $record): bool => $record->subject !== null && self::getSubjectUrl($record) !== null),

                Actions\DeleteAction::make()
                    ->visible(fn (): bool => User::me()?->can('delete activity log') ?? false),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => User::me()?->can('delete activity log') ?? false),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected static function formatActivityDescription(Activity $activity): string
    {
        $causer = $activity->causer;
        $causerName = $causer instanceof User ? $causer->name : null;
        $subjectName = self::getSubjectDisplayName($activity);
        $subjectLabel = self::getSubjectTypeLabel($activity->subject_type);
        $event = $activity->event ?? self::extractEventFromDescription($activity->description);

        // Semantic events get special formatting
        if ($event && in_array($event, ['confirmed', 'cancelled', 'auto_cancelled', 'rescheduled', 'payment_recorded', 'comped'])) {
            return self::formatSemanticEvent($causerName, $subjectName, $subjectLabel, $event);
        }

        // Standard CRUD events
        $identifier = $subjectName ? "{$subjectLabel} \"{$subjectName}\"" : "a {$subjectLabel}";

        return match ($event) {
            'created' => $causerName
                ? "{$causerName} created {$identifier}"
                : ucfirst($identifier) . ' was created',
            'updated' => $causerName
                ? "{$causerName} updated {$identifier}"
                : ucfirst($identifier) . ' was updated',
            'deleted' => $causerName
                ? "{$causerName} deleted {$identifier}"
                : ucfirst($identifier) . ' was deleted',
            default => $activity->description ?: ($causerName ? "{$causerName} performed an action" : 'Action performed'),
        };
    }

    protected static function formatSemanticEvent(?string $causerName, ?string $subjectName, string $subjectLabel, string $event): string
    {
        $identifier = $subjectName ? "\"{$subjectName}\"" : "a {$subjectLabel}";

        return match ($event) {
            'confirmed' => $causerName ? "{$causerName} confirmed {$identifier}" : ucfirst($identifier) . ' was confirmed',
            'cancelled' => $causerName ? "{$causerName} cancelled {$identifier}" : ucfirst($identifier) . ' was cancelled',
            'auto_cancelled' => ucfirst($identifier) . ' was auto-cancelled',
            'rescheduled' => $causerName ? "{$causerName} rescheduled {$identifier}" : ucfirst($identifier) . ' was rescheduled',
            'payment_recorded' => $causerName ? "{$causerName} recorded payment for {$identifier}" : "Payment recorded for {$identifier}",
            'comped' => $causerName ? "{$causerName} comped {$identifier}" : ucfirst($identifier) . ' was comped',
            default => $causerName ? "{$causerName} {$event} {$identifier}" : ucfirst($identifier) . " was {$event}",
        };
    }

    /**
     * Get a display name for the activity subject.
     */
    protected static function getSubjectDisplayName(Activity $activity): ?string
    {
        $subject = $activity->subject;

        if (! $subject) {
            return null;
        }

        // Try common name attributes in order of preference
        foreach (['title', 'name'] as $attr) {
            if (isset($subject->{$attr}) && $subject->{$attr}) {
                return $subject->{$attr};
            }
        }

        // Special cases for models without simple name attributes
        return match ($activity->subject_type) {
            'reservation', 'rehearsal_reservation' => self::getReservationDisplayName($subject),
            'equipment_loan' => self::getEquipmentLoanDisplayName($subject),
            default => null,
        };
    }

    protected static function getReservationDisplayName(mixed $reservation): ?string
    {
        if (! isset($reservation->reserved_at)) {
            return null;
        }

        $date = $reservation->reserved_at->format('M j');
        $time = $reservation->reserved_at->format('g:ia');

        return "{$date} at {$time}";
    }

    protected static function getEquipmentLoanDisplayName(mixed $loan): ?string
    {
        $equipment = $loan->equipment ?? null;

        return $equipment?->name;
    }

    /**
     * Get a human-readable label for a subject type.
     */
    protected static function getSubjectTypeLabel(?string $subjectType): string
    {
        return match ($subjectType) {
            'user' => 'user',
            'member_profile' => 'member profile',
            'band' => 'band',
            'event' => 'event',
            'reservation', 'rehearsal_reservation' => 'reservation',
            'equipment' => 'equipment',
            'equipment_loan' => 'equipment loan',
            'equipment_damage_report' => 'damage report',
            default => $subjectType ? str($subjectType)->replace('_', ' ')->toString() : 'record',
        };
    }

    /**
     * Extract the event type from the description if not explicitly set.
     */
    protected static function extractEventFromDescription(?string $description): ?string
    {
        if (! $description) {
            return null;
        }

        if (str_contains($description, 'created')) {
            return 'created';
        }
        if (str_contains($description, 'updated')) {
            return 'updated';
        }
        if (str_contains($description, 'deleted')) {
            return 'deleted';
        }

        return null;
    }

    protected static function getActivityIcon(Activity $activity): string
    {
        // Check semantic event first, then fall back to description matching
        if ($activity->event) {
            $icon = match ($activity->event) {
                'confirmed' => 'tabler-check',
                'cancelled', 'auto_cancelled' => 'tabler-x',
                'rescheduled' => 'tabler-calendar-repeat',
                'payment_recorded' => 'tabler-cash',
                'comped' => 'tabler-gift',
                default => null,
            };

            if ($icon) {
                return $icon;
            }
        }

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
        // Check semantic event first
        if ($activity->event) {
            $color = match ($activity->event) {
                'confirmed', 'payment_recorded' => 'success',
                'cancelled', 'auto_cancelled' => 'danger',
                'rescheduled' => 'warning',
                'comped' => 'info',
                default => null,
            };

            if ($color) {
                return $color;
            }
        }

        return match (true) {
            str_contains($activity->description, 'created') => 'success',
            str_contains($activity->description, 'updated') => 'info',
            str_contains($activity->description, 'deleted') => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the URL to view/edit the subject of an activity log entry.
     *
     * Uses Filament's panel-aware resource URL generation to find the appropriate
     * resource across all panels without hardcoding resource classes.
     */
    protected static function getSubjectUrl(Activity $activity): ?string
    {
        if (! $activity->subject instanceof Model) {
            return null;
        }

        return self::getModelUrl($activity->subject);
    }

    /**
     * Find a resource URL for a model within the current Filament panel.
     *
     * Prefers 'view' page, falls back to 'edit', then 'index'.
     */
    protected static function getModelUrl(Model $model): ?string
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel) {
            return null;
        }

        $resource = $panel->getModelResource($model);

        if (! $resource) {
            return null;
        }

        // Try view, then edit, then index
        foreach (['view', 'edit', 'index'] as $page) {
            if (array_key_exists($page, $resource::getPages())) {
                try {
                    return $panel->getResourceUrl($model, $page);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }
}
