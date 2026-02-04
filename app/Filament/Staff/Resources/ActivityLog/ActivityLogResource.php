<?php

namespace App\Filament\Staff\Resources\ActivityLog;

use App\Filament\Staff\Resources\ActivityLog\Tables\ActivityLogTable;
use App\Models\User;
use Filament\Resources\Resource;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-activity';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $pluralModelLabel = 'Activities';

    protected static ?int $navigationSort = 100;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function canAccess(): bool
    {
        return User::me()?->can('view activity log') ?? false;
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return ActivityLogTable::make($table);
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\ActivityStatsWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Don't allow manual creation
    }

    public static function canEdit($record): bool
    {
        return false; // Don't allow editing
    }

    public static function canDelete($record): bool
    {
        return User::me()?->can('delete activity log') ?? false;
    }

    /**
     * Valid subject types that still exist in the codebase.
     * Filter out legacy/removed model classes to prevent errors.
     */
    protected static array $validSubjectTypes = [
        'user',
        'member_profile',
        'band',
        'event',
        'reservation',
        'rehearsal_reservation',
        'equipment',
        'equipment_loan',
    ];

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Filter to valid subject types first, then eager load
        $query = parent::getEloquentQuery()
            ->with(['causer', 'subject'])
            ->where(function ($q) {
                $q->whereNull('subject_type')
                    ->orWhereIn('subject_type', static::$validSubjectTypes);
            });

        $currentUser = User::me();

        if (! $currentUser || ! $currentUser->can('view all activity logs')) {
            // Filter to only show activities the user is authorized to see
            $query->where(function ($subQuery) use ($currentUser) {
                $subQuery->whereHasMorph('subject', [
                    MemberProfile::class,
                ], function ($q) use ($currentUser) {
                    // Only show member profile activities if profile is visible
                    $q->whereIn('visibility', $currentUser ? ['public', 'members'] : ['public']);
                })
                    ->orWhereHasMorph('subject', [
                        Band::class,
                    ], function ($q) use ($currentUser) {
                        // Apply band visibility rules
                        if ($currentUser) {
                            $q->where(function ($bandQuery) use ($currentUser) {
                                $bandQuery->where('visibility', 'public')
                                    ->orWhere('visibility', 'members')
                                    ->orWhere(function ($privateQuery) use ($currentUser) {
                                        $privateQuery->where('visibility', 'private')
                                            ->where('owner_id', $currentUser->id);
                                    });
                            });
                        } else {
                            $q->where('visibility', 'public');
                        }
                    })
                    ->orWhereHasMorph('subject', [
                        \CorvMC\Events\Models\Event::class,
                    ], function ($q) use ($currentUser) {
                        // Only published productions, or user's own productions, or if user has permission
                        $q->where(function ($prodQuery) use ($currentUser) {
                            $prodQuery->whereNotNull('published_at')
                                ->where('published_at', '<=', now());

                            if ($currentUser) {
                                $prodQuery->orWhere('manager_id', $currentUser->id);

                                if ($currentUser->can('view productions')) {
                                    $prodQuery->orWhereRaw('1=1'); // Show all
                                }
                            }
                        });
                    })
                    ->orWhereHasMorph('subject', [
                        \CorvMC\SpaceManagement\Models\Reservation::class,
                    ], function ($q) use ($currentUser) {
                        if (! $currentUser) {
                            $q->whereRaw('1=0'); // No reservations visible to guests

                            return;
                        }

                        // Own reservations or if user has permission
                        $q->where('user_id', $currentUser->id);

                        if ($currentUser->can('view reservations')) {
                            $q->orWhereRaw('1=1'); // Show all
                        }
                    })
                    ->orWhereHasMorph('subject', [
                        User::class,
                    ], function ($q) {
                        // User activities are generally visible (registration, etc.)
                        $q->whereRaw('1=1');
                    })
                    ->orWhereNull('subject_type'); // System activities
            });
        }

        return $query;
    }
}
