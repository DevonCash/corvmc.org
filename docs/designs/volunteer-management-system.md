# Volunteer Management System Design

## Overview

A comprehensive volunteer management system for the Corvallis Music Collective to coordinate volunteers for events, track hours, manage shifts, and recognize contributions.

## Core Features

### 1. Volunteer Roles & Opportunities
- Define volunteer roles (e.g., door staff, sound tech, merchandise, setup/teardown)
- Create opportunities linked to productions or standalone
- Set required skills/experience levels
- Define time commitments and shift schedules

### 2. Shift Management
- Schedule shifts with start/end times
- Set volunteer capacity per shift
- Track sign-ups and cancellations
- Send automated reminders before shifts

### 3. Hour Tracking & Recognition
- Log volunteer hours automatically from completed shifts
- Manual hour entry for ad-hoc volunteering
- Milestone badges and recognition levels
- Convert volunteer hours to practice space credits (e.g., 1 credit per 2 hours volunteered)

### 4. Communication
- Shift reminders via email/SMS
- Volunteer announcements and newsletters
- Emergency contact management
- Shift swap requests and notifications

### 5. Reporting & Analytics
- Volunteer hour summaries by person/period
- No-show and cancellation tracking
- Popular shift types and trends
- Impact reports for grant applications

## Database Schema

### Tables

#### `volunteer_roles`
```
id - bigint primary key
name - string (e.g., "Sound Tech", "Door Staff")
description - text
required_skills - json (array of skill tags)
experience_level - enum (beginner, intermediate, advanced)
is_active - boolean
created_at, updated_at, deleted_at
```

#### `volunteer_opportunities`
```
id - bigint primary key
title - string
description - text
opportunitable_type - string (Production, null for standalone)
opportunitable_id - bigint nullable
status - enum (draft, published, completed, cancelled)
location - string nullable
contact_user_id - foreign key to users
created_by - foreign key to users
published_at - timestamp nullable
created_at, updated_at, deleted_at
```

#### `volunteer_shifts`
```
id - bigint primary key
volunteer_opportunity_id - foreign key
volunteer_role_id - foreign key
start_time - timestamp
end_time - timestamp
capacity - integer (max volunteers)
location - string nullable
notes - text nullable
status - enum (scheduled, in_progress, completed, cancelled)
created_at, updated_at, deleted_at
```

#### `volunteer_shift_signups`
```
id - bigint primary key
volunteer_shift_id - foreign key
user_id - foreign key
status - enum (pending, confirmed, completed, cancelled, no_show)
signed_up_at - timestamp
confirmed_at - timestamp nullable
cancelled_at - timestamp nullable
cancellation_reason - text nullable
hours_worked - decimal(5,2) nullable (for partial shifts)
notes - text nullable
reminder_sent_at - timestamp nullable
created_at, updated_at
```

#### `volunteer_hours`
```
id - bigint primary key
user_id - foreign key
volunteer_shift_signup_id - foreign key nullable
hours - decimal(5,2)
date - date
type - enum (shift, manual, adjustment)
description - text
approved_by - foreign key to users nullable
approved_at - timestamp nullable
created_at, updated_at, deleted_at
```

#### `volunteer_milestones`
```
id - bigint primary key
name - string (e.g., "10 Hour Hero", "50 Hour Champion")
description - text
hours_required - integer
badge_icon - string nullable
reward_credits - integer (practice space credits awarded)
is_active - boolean
created_at, updated_at
```

#### `volunteer_milestone_achievements`
```
id - bigint primary key
user_id - foreign key
volunteer_milestone_id - foreign key
achieved_at - timestamp
credits_awarded - integer
created_at, updated_at
```

## Models & Relationships

### VolunteerRole
```php
class VolunteerRole extends Model
{
    use SoftDeletes, HasTags;

    protected $casts = [
        'required_skills' => 'array',
        'is_active' => 'boolean',
    ];

    public function shifts()
    {
        return $this->hasMany(VolunteerShift::class);
    }
}
```

### VolunteerOpportunity
```php
class VolunteerOpportunity extends Model
{
    use SoftDeletes, LogsActivity;

    public function opportunitable()
    {
        return $this->morphTo();
    }

    public function shifts()
    {
        return $this->hasMany(VolunteerShift::class);
    }

    public function contactPerson()
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

### VolunteerShift
```php
class VolunteerShift extends Model
{
    use SoftDeletes, LogsActivity;

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'capacity' => 'integer',
    ];

    public function opportunity()
    {
        return $this->belongsTo(VolunteerOpportunity::class);
    }

    public function role()
    {
        return $this->belongsTo(VolunteerRole::class);
    }

    public function signups()
    {
        return $this->hasMany(VolunteerShiftSignup::class);
    }

    public function availableSpots(): int
    {
        return $this->capacity - $this->signups()->whereIn('status', ['confirmed', 'pending'])->count();
    }

    public function isFull(): bool
    {
        return $this->availableSpots() <= 0;
    }
}
```

### VolunteerShiftSignup
```php
class VolunteerShiftSignup extends Model
{
    use LogsActivity;

    protected $casts = [
        'signed_up_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'hours_worked' => 'decimal:2',
    ];

    public function shift()
    {
        return $this->belongsTo(VolunteerShift::class, 'volunteer_shift_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function volunteerHours()
    {
        return $this->hasOne(VolunteerHours::class);
    }
}
```

### VolunteerHours
```php
class VolunteerHours extends Model
{
    use SoftDeletes, LogsActivity;

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signup()
    {
        return $this->belongsTo(VolunteerShiftSignup::class, 'volunteer_shift_signup_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
```

### VolunteerMilestone
```php
class VolunteerMilestone extends Model
{
    protected $casts = [
        'hours_required' => 'integer',
        'reward_credits' => 'integer',
        'is_active' => 'boolean',
    ];

    public function achievements()
    {
        return $this->hasMany(VolunteerMilestoneAchievement::class);
    }
}
```

### VolunteerMilestoneAchievement
```php
class VolunteerMilestoneAchievement extends Model
{
    protected $casts = [
        'achieved_at' => 'datetime',
        'credits_awarded' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function milestone()
    {
        return $this->belongsTo(VolunteerMilestone::class);
    }
}
```

## Service Layer

### VolunteerService

```php
class VolunteerService
{
    /**
     * Sign up a user for a volunteer shift
     */
    public function signUpForShift(User $user, VolunteerShift $shift): VolunteerShiftSignup
    {
        if ($shift->isFull()) {
            throw new Exception('This shift is already full');
        }

        if ($this->hasConflictingShift($user, $shift)) {
            throw new Exception('You have a conflicting shift at this time');
        }

        return VolunteerShiftSignup::create([
            'volunteer_shift_id' => $shift->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
            'signed_up_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Cancel a shift signup
     */
    public function cancelSignup(VolunteerShiftSignup $signup, ?string $reason = null): void
    {
        $signup->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Mark shift as completed and log hours
     */
    public function completeShift(VolunteerShiftSignup $signup, ?float $hoursWorked = null): VolunteerHours
    {
        $shift = $signup->shift;
        $hours = $hoursWorked ?? $shift->end_time->diffInHours($shift->start_time, absolute: true);

        $signup->update([
            'status' => 'completed',
            'hours_worked' => $hours,
        ]);

        $volunteerHours = VolunteerHours::create([
            'user_id' => $signup->user_id,
            'volunteer_shift_signup_id' => $signup->id,
            'hours' => $hours,
            'date' => $shift->start_time->toDateString(),
            'type' => 'shift',
            'description' => "Volunteered as {$shift->role->name} for {$shift->opportunity->title}",
            'approved_at' => now(),
        ]);

        $this->checkMilestones($signup->user);

        return $volunteerHours;
    }

    /**
     * Log manual volunteer hours
     */
    public function logManualHours(User $user, float $hours, string $date, string $description, ?User $approver = null): VolunteerHours
    {
        $volunteerHours = VolunteerHours::create([
            'user_id' => $user->id,
            'hours' => $hours,
            'date' => $date,
            'type' => 'manual',
            'description' => $description,
            'approved_by' => $approver?->id,
            'approved_at' => $approver ? now() : null,
        ]);

        if ($approver) {
            $this->checkMilestones($user);
        }

        return $volunteerHours;
    }

    /**
     * Get total volunteer hours for a user
     */
    public function getTotalHours(User $user, ?Carbon $since = null): float
    {
        $query = VolunteerHours::where('user_id', $user->id)
            ->whereNotNull('approved_at');

        if ($since) {
            $query->where('date', '>=', $since->toDateString());
        }

        return $query->sum('hours');
    }

    /**
     * Check and award milestones
     */
    protected function checkMilestones(User $user): void
    {
        $totalHours = $this->getTotalHours($user);

        $milestones = VolunteerMilestone::where('is_active', true)
            ->where('hours_required', '<=', $totalHours)
            ->whereDoesntHave('achievements', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        foreach ($milestones as $milestone) {
            VolunteerMilestoneAchievement::create([
                'user_id' => $user->id,
                'volunteer_milestone_id' => $milestone->id,
                'achieved_at' => now(),
                'credits_awarded' => $milestone->reward_credits,
            ]);

            if ($milestone->reward_credits > 0) {
                app(CreditService::class)->allocateCredits(
                    $user,
                    'free_hours',
                    $milestone->reward_credits,
                    "Volunteer milestone achieved: {$milestone->name}"
                );
            }

            // Send notification
            $user->notify(new VolunteerMilestoneAchievedNotification($milestone));
        }
    }

    /**
     * Check for shift conflicts
     */
    protected function hasConflictingShift(User $user, VolunteerShift $shift): bool
    {
        return VolunteerShiftSignup::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereHas('shift', function ($query) use ($shift) {
                $query->where(function ($q) use ($shift) {
                    $q->whereBetween('start_time', [$shift->start_time, $shift->end_time])
                        ->orWhereBetween('end_time', [$shift->start_time, $shift->end_time])
                        ->orWhere(function ($q2) use ($shift) {
                            $q2->where('start_time', '<=', $shift->start_time)
                                ->where('end_time', '>=', $shift->end_time);
                        });
                });
            })
            ->exists();
    }
}
```

## Filament Resources

### VolunteerOpportunityResource
- Location: `/member/volunteer-opportunities`
- List volunteers can view and sign up for shifts
- Create/edit for admins and volunteer coordinators
- Integrated calendar view of shifts
- Quick signup actions in table

### VolunteerShiftResource
- Location: `/member/volunteer-shifts`
- Calendar view with shift details
- Signup management
- Attendance tracking
- No-show marking

### VolunteerHoursResource
- Location: `/member/volunteer-hours`
- Personal hour tracking for members
- Admin view for all members
- Manual entry and approval workflow
- Export functionality for reporting

### VolunteerMilestoneResource
- Location: `/member/volunteer-milestones`
- Admin-only configuration
- Preview of user achievements
- Credit reward configuration

## Commands

### Send Shift Reminders
```bash
php artisan volunteer:send-shift-reminders
```
- Runs daily via scheduler
- Sends reminders 24 hours before shifts
- Marks reminder_sent_at timestamp

### Complete Past Shifts
```bash
php artisan volunteer:complete-past-shifts
```
- Runs daily via scheduler
- Auto-completes shifts that ended in the past
- Logs hours based on shift duration
- Checks for no-shows

### Test Volunteer System
```bash
php artisan test:volunteer-system [--clean] [--dry-run]
```
- Create test opportunities, shifts, and signups
- Test milestone achievement
- Test credit rewards
- Cleanup test data with --clean

## Notifications

### VolunteerShiftReminderNotification
- Sent 24 hours before shift
- Includes shift details and location
- Link to cancel or view details

### VolunteerShiftCancelledNotification
- Sent when a shift is cancelled by organizers
- Suggests alternative shifts

### VolunteerMilestoneAchievedNotification
- Sent when user reaches milestone
- Shows badge and credit rewards
- Encourages continued volunteering

### VolunteerShiftSwapRequestNotification
- Sent when another volunteer requests to swap shifts
- Approve/decline actions

## Permissions

### Roles
- `view_volunteer_opportunities` - All members
- `manage_volunteer_opportunities` - Admins, volunteer coordinators
- `view_volunteer_hours` - Own hours for members, all for admins
- `approve_volunteer_hours` - Admins, volunteer coordinators
- `manage_volunteer_milestones` - Admins only

## Integration Points

### Productions
- Automatically create volunteer opportunities for productions
- Link shifts to production events
- Show volunteers in production management

### Credits System
- Award practice space credits for volunteer milestones
- Track credit allocations from volunteering
- Display volunteer hours in user profile

### Member Profiles
- Show volunteer hours and milestones
- Display badges on public profiles
- Volunteer leaderboard widget

## Implementation Estimates

### Phase 1: Core Models & Basic Functionality (8-12 hours)
- Database migrations
- Model definitions with relationships
- Basic VolunteerService methods
- Seeders for testing

### Phase 2: Filament Resources (10-15 hours)
- VolunteerOpportunityResource with forms/tables
- VolunteerShiftResource with calendar view
- VolunteerHoursResource with approval workflow
- VolunteerMilestoneResource

### Phase 3: Automation & Integration (6-10 hours)
- Scheduled commands for reminders and completion
- Integration with Credits System
- Production integration
- Notifications

### Phase 4: Testing & Polish (4-6 hours)
- Test command implementation
- Feature tests
- UI/UX refinements
- Documentation

**Total Estimate: 28-43 hours**

## Future Enhancements

### Phase 2 Features
- Volunteer skill certifications (e.g., sound tech certification)
- Shift swapping marketplace
- Recurring shift templates
- Volunteer groups and teams
- Impact reports and visualizations
- SMS notifications via Twilio
- Mobile app integration
- Volunteer feedback and ratings
- Equipment checkout for volunteers
- Volunteer appreciation events

### Advanced Features
- AI-based shift recommendations
- Predictive analytics for volunteer retention
- Integration with external volunteer platforms
- Volunteer onboarding workflows
- Background check tracking
- Custom form fields per opportunity
- Multi-language support
