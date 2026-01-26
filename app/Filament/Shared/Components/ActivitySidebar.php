<?php

namespace App\Filament\Shared\Components;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;

class ActivitySidebar
{
    public static function render(): string
    {
        $activities = self::getContextualActivities();
        $context = self::getCurrentContext();

        return view('filament.components.activity-sidebar', [
            'activities' => $activities,
            'context' => $context,
        ])->render();
    }

    protected static function getContextualActivities()
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            return collect();
        }

        $context = self::getCurrentContext();

        $query = Activity::with(['subject', 'causer.profile'])
            ->latest()
            ->limit(100); // Get more to account for filtering

        // Apply context-specific filters
        switch ($context['type']) {
            case 'band':
                if ($context['record_id']) {
                    // If record_id is a slug, find the actual band ID
                    if (is_string($context['record_id']) && ! is_numeric($context['record_id'])) {
                        $band = \App\Models\Band::where('slug', $context['record_id'])->first();
                        $bandId = $band ? $band->id : null;
                    } else {
                        $bandId = $context['record_id'];
                    }

                    if ($bandId) {
                        $query->where('subject_type', 'App\\Models\\Band')
                            ->where('subject_id', $bandId);
                    }
                }
                break;

            case 'member':
                if ($context['record_id']) {
                    // Get the member profile and user ID outside the query to avoid N+1
                    $memberProfile = \App\Models\MemberProfile::find($context['record_id']);

                    $query->where(function ($q) use ($context, $memberProfile) {
                        // Direct member profile activities
                        $q->where('subject_type', 'App\\Models\\MemberProfile')
                            ->where('subject_id', $context['record_id']);

                        // User activities related to this member profile
                        if ($memberProfile && $memberProfile->user_id) {
                            $q->orWhere(function ($userQuery) use ($memberProfile) {
                                $userQuery->where('subject_type', 'App\\Models\\User')
                                    ->where('subject_id', $memberProfile->user_id);
                            });
                        }
                    });
                }
                break;

            case 'reservation':
                $query->where('subject_type', 'App\\Models\\Reservation');
                break;

            case 'production':
                if ($context['record_id']) {
                    $query->where('subject_type', 'App\\Models\\Production')
                        ->where('subject_id', $context['record_id']);
                } else {
                    $query->where('subject_type', 'App\\Models\\Production');
                }
                break;

            case 'dashboard':
            default:
                // Show all activity (existing logic)
                break;
        }

        return $query->get()
            ->filter(function (Activity $activity) use ($currentUser) {
                return self::canViewActivity($activity, $currentUser);
            })
            ->take(15)
            ->map(function (Activity $activity) {
                return [
                    'id' => $activity->id,
                    'description' => self::formatActivityDescription($activity),
                    'causer_name' => $activity->causer?->name ?? 'System',
                    'subject_type' => class_basename($activity->subject_type ?? ''),
                    'subject_id' => $activity->subject_id,
                    'created_at' => $activity->created_at,
                    'icon' => self::getActivityIcon($activity),
                    'color' => self::getActivityColor($activity),
                    'url' => self::getActivitySubjectUrl($activity),
                ];
            });
    }

    protected static function getCurrentContext(): array
    {
        $route = Route::current();
        if (! $route) {
            return [
                'type' => 'dashboard',
                'resource' => null,
                'record_id' => null,
                'page' => 'dashboard',
            ];
        }

        $routeName = $route->getName();
        $parameters = $route->parameters();

        // Parse the route to determine context
        if (str_contains($routeName, 'resources.bands')) {
            return [
                'type' => 'band',
                'resource' => 'bands',
                'record_id' => $parameters['record'] ?? null,
                'page' => self::getPageType($routeName),
            ];
        }

        if (str_contains($routeName, 'resources.directory')) {
            return [
                'type' => 'member',
                'resource' => 'directory',
                'record_id' => $parameters['record'] ?? null,
                'page' => self::getPageType($routeName),
            ];
        }

        if (str_contains($routeName, 'resources.reservations')) {
            return [
                'type' => 'reservation',
                'resource' => 'reservations',
                'record_id' => $parameters['record'] ?? null,
                'page' => self::getPageType($routeName),
            ];
        }

        if (str_contains($routeName, 'resources.productions')) {
            return [
                'type' => 'production',
                'resource' => 'productions',
                'record_id' => $parameters['record'] ?? null,
                'page' => self::getPageType($routeName),
            ];
        }

        return [
            'type' => 'dashboard',
            'resource' => null,
            'record_id' => null,
            'page' => 'dashboard',
        ];
    }

    protected static function getPageType(string $routeName): string
    {
        if (str_contains($routeName, '.index')) {
            return 'index';
        }
        if (str_contains($routeName, '.view')) {
            return 'view';
        }
        if (str_contains($routeName, '.edit')) {
            return 'edit';
        }
        if (str_contains($routeName, '.create')) {
            return 'create';
        }

        return 'other';
    }

    // Reuse the existing activity formatting methods from ActivityFeedWidget
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
            'Member profile created' => self::formatMemberDescription($activity, $causerName, 'completed'),
            'Member profile updated' => self::formatMemberDescription($activity, $causerName, 'updated'),
            'Practice space reservation created' => self::formatReservationDescription($activity, $causerName, 'booked'),
            'Practice space reservation updated' => self::formatReservationDescription($activity, $causerName, 'updated'),
            'Practice space reservation deleted' => self::formatReservationDescription($activity, $causerName, 'cancelled'),
            default => $activity->description ?: "{$causerName} performed an action",
        };
    }

    protected static function formatProductionDescription(Activity $activity, string $causerName, string $action): string
    {
        $production = $activity->subject;
        if ($production && isset($production->title)) {
            return "{$causerName} {$action} event \"{$production->title}\"";
        }

        return "{$causerName} {$action} an event";
    }

    protected static function formatBandDescription(Activity $activity, string $causerName, string $action): string
    {
        $band = $activity->subject;
        if ($band && isset($band->name)) {
            $actionText = $action === 'created' ? 'created' : 'updated';

            return "{$causerName} {$actionText} band \"{$band->name}\"";
        }

        return "{$causerName} {$action} a band profile";
    }

    protected static function formatMemberDescription(Activity $activity, string $causerName, string $action): string
    {
        $actionText = $action === 'completed' ? 'completed their profile' : 'updated their profile';

        return "{$causerName} {$actionText}";
    }

    protected static function formatReservationDescription(Activity $activity, string $causerName, string $action): string
    {
        $currentUser = Auth::user();
        $reservation = $activity->subject;

        if (
            $currentUser && $reservation &&
            (isset($reservation->user_id) && $reservation->user_id === $currentUser->id || $currentUser->can('view reservations'))
        ) {
            $actionText = match ($action) {
                'booked' => 'booked the practice space',
                'updated' => 'updated their reservation',
                'cancelled' => 'cancelled a reservation',
                default => "{$action} a reservation",
            };

            return "{$causerName} {$actionText}";
        }

        return 'Practice space activity';
    }

    protected static function getActivityIcon(Activity $activity): string
    {
        return match ($activity->description) {
            'User account created' => 'tabler-user-plus',
            'User account updated' => 'tabler-user-edit',
            'Production created' => 'tabler-calendar-plus',
            'Production updated' => 'tabler-calendar-up',
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

    protected static function getActivitySubjectUrl(Activity $activity): ?string
    {
        if (! $activity->subject || ! $activity->subject_id) {
            return null;
        }

        try {
            return match ($activity->subject_type) {
                'App\\Models\\Band' => route('filament.member.resources.bands.view', ['record' => $activity->subject]),
                'App\\Models\\MemberProfile' => route('filament.member.resources.directory.view', ['record' => $activity->subject_id]),
                'App\\Models\\Production' => route('filament.member.resources.productions.view', ['record' => $activity->subject_id]),
                'App\\Models\\Reservation' => route('filament.member.resources.reservations.index'),
                'App\\Models\\User' => (isset($activity->subject->profile) && $activity->subject->profile?->id) ?
                    route('filament.member.resources.directory.view', ['record' => $activity->subject->profile->id]) : null,
                default => null,
            };
        } catch (\Exception $e) {
            // If route generation fails, return null (no link)
            return null;
        }
    }

    protected static function canViewActivity(Activity $activity, $currentUser): bool
    {
        // Simplified version - reuse existing logic from ActivityFeedWidget
        if (! $activity->subject) {
            return false;
        }

        // For now, show all activities that have subjects
        // You can add more specific visibility rules here
        return true;
    }
}
