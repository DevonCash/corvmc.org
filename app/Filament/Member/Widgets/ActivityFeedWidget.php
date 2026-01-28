<?php

namespace App\Filament\Member\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class ActivityFeedWidget extends Widget
{
    protected string $view = 'filament.widgets.activity-feed-widget';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected static ?int $sort = -1;

    public function getActivities()
    {
        $currentUser = Auth::user();

        $activities = Activity::with(['subject', 'causer'])
            ->latest()
            ->limit(50) // Get more to account for filtering
            ->get()
            ->filter(function (Activity $activity) use ($currentUser) {
                return $this->canViewActivity($activity, $currentUser);
            });

        // Show activities with minimum of 3, maximum that fits in scroll area
        return $activities->take(10) // Reasonable limit for scrollable area
            ->map(function (Activity $activity) {
                return [
                    'id' => $activity->id,
                    'description' => $this->formatActivityDescription($activity),
                    'causer_name' => $activity->causer?->name ?? 'System',
                    'subject_type' => class_basename($activity->subject_type ?? ''),
                    'subject_id' => $activity->subject_id,
                    'created_at' => $activity->created_at,
                    'icon' => $this->getActivityIcon($activity),
                    'color' => $this->getActivityColor($activity),
                ];
            });
    }

    protected function formatActivityDescription(Activity $activity): string
    {
        $causerName = $activity->causer?->name ?? 'System';

        return match ($activity->description) {
            'User account created' => "{$causerName} joined the community",
            'User account updated' => "{$causerName} updated their account",
            'Production created' => $this->formatProductionDescription($activity, $causerName, 'created'),
            'Production updated' => $this->formatProductionDescription($activity, $causerName, 'updated'),
            'Production deleted' => "{$causerName} removed an event",
            'Band profile created' => $this->formatBandDescription($activity, $causerName, 'created'),
            'Band profile updated' => $this->formatBandDescription($activity, $causerName, 'updated'),
            'Band profile deleted' => "{$causerName} removed a band profile",
            'Member profile created' => $this->formatMemberDescription($activity, $causerName, 'completed'),
            'Member profile updated' => $this->formatMemberDescription($activity, $causerName, 'updated'),
            'Practice space reservation created' => $this->formatReservationDescription($activity, $causerName, 'booked'),
            'Practice space reservation updated' => $this->formatReservationDescription($activity, $causerName, 'updated'),
            'Practice space reservation deleted' => $this->formatReservationDescription($activity, $causerName, 'cancelled'),
            default => $activity->description ?: "{$causerName} performed an action",
        };
    }

    protected function formatProductionDescription(Activity $activity, string $causerName, string $action): string
    {
        $event = $activity->subject;
        if ($event && isset($event->title)) {
            return "{$causerName} {$action} event \"{$event->title}\"";
        }

        return "{$causerName} {$action} an event";
    }

    protected function formatBandDescription(Activity $activity, string $causerName, string $action): string
    {
        $band = $activity->subject;
        if ($band && isset($band->name)) {
            $actionText = $action === 'created' ? 'created' : 'updated';

            return "{$causerName} {$actionText} band \"{$band->name}\"";
        }

        return "{$causerName} {$action} a band profile";
    }

    protected function formatMemberDescription(Activity $activity, string $causerName, string $action): string
    {
        $actionText = $action === 'completed' ? 'completed their profile' : 'updated their profile';

        return "{$causerName} {$actionText}";
    }

    protected function formatReservationDescription(Activity $activity, string $causerName, string $action): string
    {
        $currentUser = Auth::user();
        $reservation = $activity->subject;

        // Only show details for own reservations or if user has permission
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

        // Generic message for others
        return 'Practice space activity';
    }

    protected function getActivityIcon(Activity $activity): string
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

    protected function getActivityColor(Activity $activity): string
    {
        return match (true) {
            str_contains($activity->description, 'created') => 'success',
            str_contains($activity->description, 'updated') => 'info',
            str_contains($activity->description, 'deleted') => 'danger',
            default => 'gray',
        };
    }

    protected function canViewActivity(Activity $activity, $currentUser): bool
    {
        // If no subject (activity was about deleted model), hide it
        if (! $activity->subject) {
            return false;
        }

        // Handle different subject types
        $subjectType = $activity->subject_type;
        $subject = $activity->subject;

        return match ($subjectType) {
            'App\\Models\\User' => $this->canViewUserActivity($activity, $currentUser),
            'App\\Models\\MemberProfile' => $this->canViewMemberProfileActivity($subject, $currentUser),
            'App\\Models\\Band' => $this->canViewBandProfileActivity($subject, $currentUser),
            'App\\Models\\Production' => $this->canViewProductionActivity($subject, $currentUser),
            'App\\Models\\Reservation' => $this->canViewReservationActivity($subject, $currentUser),
            default => true, // Allow other activity types by default
        };
    }

    protected function canViewUserActivity(Activity $activity, $currentUser): bool
    {
        // User registration activities are generally public
        if (str_contains($activity->description, 'created')) {
            return true;
        }

        // Only show profile updates if they're not too private
        return true;
    }

    protected function canViewMemberProfileActivity($memberProfile, $currentUser): bool
    {
        if (! $memberProfile) {
            return false;
        }

        // Use the existing visibility logic
        return $memberProfile->isVisible($currentUser);
    }

    protected function canViewBandProfileActivity($bandProfile, $currentUser): bool
    {
        if (! $bandProfile) {
            return false;
        }

        // Apply band visibility rules
        if ($bandProfile->visibility === 'public') {
            return true;
        }

        if (! $currentUser) {
            return false;
        }

        if ($bandProfile->visibility === 'members') {
            return true; // Any authenticated user
        }

        if ($bandProfile->visibility === 'private') {
            // Only band members/owners
            return $bandProfile->owner_id === $currentUser->id ||
                $bandProfile->members()->wherePivot('user_id', $currentUser->id)->exists();
        }

        return false;
    }

    protected function canViewProductionActivity($event, $currentUser): bool
    {
        if (! $event) {
            return false;
        }

        // Productions are generally visible if published
        if ($event->isPublished()) {
            return true;
        }

        // Unpublished productions only visible to manager and staff
        if ($currentUser) {
            return $event->manager_id === $currentUser->id ||
                $currentUser->can('view productions');
        }

        return false;
    }

    protected function canViewReservationActivity($reservation, $currentUser): bool
    {
        if (! $reservation || ! $currentUser) {
            return false;
        }

        // Own reservations are always visible
        if ($reservation->user_id === $currentUser->id) {
            return true;
        }

        // Staff with permission can see all reservation activities
        if ($currentUser->can('view reservations')) {
            return true;
        }

        // Hide other users' reservation details
        return false;
    }
}
