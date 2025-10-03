# Unified RSVP System Design

## Overview

A polymorphic RSVP system that works across all event types in the application: Productions, Community Programs, Community Events, and any future event types. Provides consistent RSVP functionality with capacity management, waitlists, reminders, and attendance tracking.

## Goals

- **Universal:** Single RSVP system for all event types
- **Polymorphic:** Events can be any model (Production, ProgramSession, CommunityEvent, etc.)
- **Flexible:** Support different RSVP rules per event type
- **Consistent UX:** Same RSVP experience across the platform
- **Integrated:** Unified calendar view, notifications, and dashboard widgets

## Database Schema

### Unified RSVP Tables

#### `rsvps`
```
id - bigint primary key
rsvpable_type - string (Production, ProgramSession, CommunityEvent, etc.)
rsvpable_id - bigint
user_id - foreign key to users
status - enum (going, maybe, not_going, waitlist)
rsvp_at - timestamp
notes - text nullable (plus-ones, dietary restrictions, etc.)
plus_ones - integer nullable (default 0)
reminder_sent_at - timestamp nullable
created_at, updated_at
unique (rsvpable_type, rsvpable_id, user_id)
```

#### `attendances`
```
id - bigint primary key
attendable_type - string (Production, ProgramSession, CommunityEvent, etc.)
attendable_id - bigint
user_id - foreign key to users
attended - boolean
checked_in_at - timestamp nullable
check_in_method - enum (manual, qr_code, self_checkin) nullable
notes - text nullable
created_at, updated_at
unique (attendable_type, attendable_id, user_id)
```

#### `event_settings`
```
id - bigint primary key
eventable_type - string
eventable_id - bigint
rsvp_enabled - boolean (default true)
rsvp_required - boolean (default false)
rsvp_deadline - timestamp nullable
capacity - integer nullable (null = unlimited)
waitlist_enabled - boolean (default true)
allow_plus_ones - boolean (default false)
max_plus_ones - integer nullable
attendance_tracking_enabled - boolean (default true)
visibility - enum (public, members_only, invite_only)
auto_approve_rsvps - boolean (default true)
settings - json (event-type-specific settings)
created_at, updated_at
unique (eventable_type, eventable_id)
```

#### `event_invitations` (for invite-only events)
```
id - bigint primary key
invitable_type - string
invitable_id - bigint
user_id - foreign key to users nullable
email - string nullable (for non-members)
invited_by - foreign key to users
status - enum (pending, accepted, declined, expired)
token - string unique (for email invitations)
expires_at - timestamp nullable
sent_at - timestamp nullable
responded_at - timestamp nullable
created_at, updated_at
```

#### `rsvp_questions` (custom fields per event)
```
id - bigint primary key
questionable_type - string
questionable_id - bigint
question - text
field_type - enum (text, textarea, select, checkbox, radio)
options - json nullable (for select/radio/checkbox)
required - boolean (default false)
position - integer
created_at, updated_at
```

#### `rsvp_responses`
```
id - bigint primary key
rsvp_id - foreign key to rsvps
rsvp_question_id - foreign key to rsvp_questions
response - text
created_at, updated_at
```

## Trait for Event Models

### HasRsvps Trait

Add this trait to any model that should support RSVPs:

```php
trait HasRsvps
{
    /**
     * Boot the trait
     */
    protected static function bootHasRsvps()
    {
        static::created(function ($model) {
            // Auto-create event settings with defaults
            $model->eventSettings()->firstOrCreate([], [
                'rsvp_enabled' => true,
                'rsvp_required' => false,
                'waitlist_enabled' => true,
                'attendance_tracking_enabled' => true,
                'visibility' => 'public',
                'auto_approve_rsvps' => true,
            ]);
        });
    }

    /**
     * Get event settings
     */
    public function eventSettings()
    {
        return $this->morphOne(EventSetting::class, 'eventable');
    }

    /**
     * Get all RSVPs
     */
    public function rsvps()
    {
        return $this->morphMany(Rsvp::class, 'rsvpable');
    }

    /**
     * Get confirmed RSVPs (going + plus ones)
     */
    public function confirmedRsvps()
    {
        return $this->rsvps()->where('status', 'going');
    }

    /**
     * Get attendances
     */
    public function attendances()
    {
        return $this->morphMany(Attendance::class, 'attendable');
    }

    /**
     * Get invitations
     */
    public function invitations()
    {
        return $this->morphMany(EventInvitation::class, 'invitable');
    }

    /**
     * Get RSVP questions
     */
    public function rsvpQuestions()
    {
        return $this->morphMany(RsvpQuestion::class, 'questionable')->orderBy('position');
    }

    /**
     * Get total confirmed attendee count (including plus ones)
     */
    public function getConfirmedAttendeeCount(): int
    {
        return $this->confirmedRsvps()
            ->get()
            ->sum(fn($rsvp) => 1 + ($rsvp->plus_ones ?? 0));
    }

    /**
     * Get available spots
     */
    public function getAvailableSpots(): ?int
    {
        $settings = $this->eventSettings;

        if (!$settings || !$settings->capacity) {
            return null; // Unlimited
        }

        return max(0, $settings->capacity - $this->getConfirmedAttendeeCount());
    }

    /**
     * Check if event is at capacity
     */
    public function isAtCapacity(): bool
    {
        $available = $this->getAvailableSpots();
        return $available !== null && $available <= 0;
    }

    /**
     * Check if user has RSVP'd
     */
    public function hasRsvp(User $user): bool
    {
        return $this->rsvps()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Get user's RSVP
     */
    public function getRsvpForUser(User $user): ?Rsvp
    {
        return $this->rsvps()
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Check if RSVPs are open
     */
    public function rsvpsAreOpen(): bool
    {
        $settings = $this->eventSettings;

        if (!$settings || !$settings->rsvp_enabled) {
            return false;
        }

        if ($settings->rsvp_deadline && now()->isAfter($settings->rsvp_deadline)) {
            return false;
        }

        // Event-specific logic (e.g., don't allow RSVP after event starts)
        if (method_exists($this, 'hasStarted') && $this->hasStarted()) {
            return false;
        }

        return true;
    }

    /**
     * Get event title (implement in each model)
     */
    abstract public function getEventTitle(): string;

    /**
     * Get event date/time (implement in each model)
     */
    abstract public function getEventStartDateTime(): Carbon;

    /**
     * Get event end date/time (implement in each model)
     */
    abstract public function getEventEndDateTime(): Carbon;

    /**
     * Get event location (implement in each model)
     */
    abstract public function getEventLocation(): ?string;
}
```

## Models

### Rsvp
```php
class Rsvp extends Model
{
    use LogsActivity;

    protected $casts = [
        'rsvp_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'plus_ones' => 'integer',
    ];

    public function rsvpable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function responses()
    {
        return $this->hasMany(RsvpResponse::class);
    }

    /**
     * Get total attendee count including plus ones
     */
    public function getTotalAttendeesAttribute(): int
    {
        return 1 + ($this->plus_ones ?? 0);
    }

    /**
     * Check if user is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'going';
    }

    /**
     * Check if user is waitlisted
     */
    public function isWaitlisted(): bool
    {
        return $this->status === 'waitlist';
    }

    /**
     * Get event details via polymorphic relationship
     */
    public function getEventAttribute()
    {
        return $this->rsvpable;
    }
}
```

### Attendance
```php
class Attendance extends Model
{
    use LogsActivity;

    protected $casts = [
        'attended' => 'boolean',
        'checked_in_at' => 'datetime',
    ];

    public function attendable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check in a user
     */
    public function checkIn(string $method = 'manual'): void
    {
        $this->update([
            'attended' => true,
            'checked_in_at' => now(),
            'check_in_method' => $method,
        ]);
    }
}
```

### EventSetting
```php
class EventSetting extends Model
{
    protected $casts = [
        'rsvp_enabled' => 'boolean',
        'rsvp_required' => 'boolean',
        'rsvp_deadline' => 'datetime',
        'capacity' => 'integer',
        'waitlist_enabled' => 'boolean',
        'allow_plus_ones' => 'boolean',
        'max_plus_ones' => 'integer',
        'attendance_tracking_enabled' => 'boolean',
        'auto_approve_rsvps' => 'boolean',
        'settings' => 'array',
    ];

    public function eventable()
    {
        return $this->morphTo();
    }
}
```

### EventInvitation
```php
class EventInvitation extends Model
{
    use LogsActivity;

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($invitation) {
            if (!$invitation->token) {
                $invitation->token = Str::random(64);
            }
            if (!$invitation->expires_at) {
                $invitation->expires_at = now()->addDays(30);
            }
        });
    }

    public function invitable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    /**
     * Accept invitation
     */
    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    /**
     * Decline invitation
     */
    public function decline(): void
    {
        $this->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }
}
```

### RsvpQuestion
```php
class RsvpQuestion extends Model
{
    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'position' => 'integer',
    ];

    public function questionable()
    {
        return $this->morphTo();
    }

    public function responses()
    {
        return $this->hasMany(RsvpResponse::class);
    }
}
```

### RsvpResponse
```php
class RsvpResponse extends Model
{
    public function rsvp()
    {
        return $this->belongsTo(Rsvp::class);
    }

    public function question()
    {
        return $this->belongsTo(RsvpQuestion::class);
    }
}
```

## Service Layer

### RsvpService

```php
class RsvpService
{
    /**
     * Create or update RSVP for an event
     */
    public function rsvp(
        User $user,
        Model $event,
        string $status = 'going',
        ?int $plusOnes = null,
        ?string $notes = null,
        array $questionResponses = []
    ): Rsvp {
        if (!$this->canRsvp($user, $event)) {
            throw new Exception('RSVP is not allowed for this event');
        }

        $settings = $event->eventSettings;

        // Validate plus ones
        if ($plusOnes && !$settings->allow_plus_ones) {
            throw new Exception('Plus ones are not allowed for this event');
        }

        if ($plusOnes && $settings->max_plus_ones && $plusOnes > $settings->max_plus_ones) {
            throw new Exception("Maximum {$settings->max_plus_ones} plus ones allowed");
        }

        // Check capacity
        $requestedSpots = 1 + ($plusOnes ?? 0);
        if ($status === 'going' && !$this->hasCapacity($event, $requestedSpots, $user)) {
            if ($settings->waitlist_enabled) {
                $status = 'waitlist';
            } else {
                throw new Exception('This event is at capacity');
            }
        }

        $rsvp = Rsvp::updateOrCreate(
            [
                'rsvpable_type' => get_class($event),
                'rsvpable_id' => $event->id,
                'user_id' => $user->id,
            ],
            [
                'status' => $status,
                'rsvp_at' => now(),
                'notes' => $notes,
                'plus_ones' => $plusOnes,
            ]
        );

        // Save question responses
        if (!empty($questionResponses)) {
            $this->saveQuestionResponses($rsvp, $questionResponses);
        }

        // Send confirmation
        if ($status === 'going') {
            $user->notify(new RsvpConfirmedNotification($event));
        } elseif ($status === 'waitlist') {
            $user->notify(new RsvpWaitlistedNotification($event));
        }

        // Log activity
        activity()
            ->performedOn($event)
            ->causedBy($user)
            ->withProperties(['status' => $status, 'plus_ones' => $plusOnes])
            ->log('rsvp_' . $status);

        return $rsvp;
    }

    /**
     * Cancel RSVP and promote waitlist if needed
     */
    public function cancelRsvp(Rsvp $rsvp): void
    {
        $event = $rsvp->rsvpable;
        $wasGoing = $rsvp->status === 'going';
        $spotsFreed = $rsvp->total_attendees;

        $rsvp->update(['status' => 'not_going']);

        // Promote waitlist
        if ($wasGoing && $event->eventSettings->waitlist_enabled) {
            $this->promoteFromWaitlist($event, $spotsFreed);
        }

        activity()
            ->performedOn($event)
            ->causedBy($rsvp->user)
            ->log('rsvp_cancelled');
    }

    /**
     * Check if user can RSVP
     */
    protected function canRsvp(User $user, Model $event): bool
    {
        $settings = $event->eventSettings;

        if (!$settings || !$settings->rsvp_enabled) {
            return false;
        }

        if (!$event->rsvpsAreOpen()) {
            return false;
        }

        // Check visibility permissions
        if ($settings->visibility === 'members_only' && !$user->isSustainingMember()) {
            return false;
        }

        if ($settings->visibility === 'invite_only') {
            return $event->invitations()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists();
        }

        return true;
    }

    /**
     * Check if event has capacity for requested spots
     */
    protected function hasCapacity(Model $event, int $requestedSpots, ?User $existingUser = null): bool
    {
        $available = $event->getAvailableSpots();

        if ($available === null) {
            return true; // Unlimited capacity
        }

        // If user already has RSVP, add their current spots back
        if ($existingUser) {
            $existingRsvp = $event->getRsvpForUser($existingUser);
            if ($existingRsvp && $existingRsvp->status === 'going') {
                $available += $existingRsvp->total_attendees;
            }
        }

        return $available >= $requestedSpots;
    }

    /**
     * Promote users from waitlist
     */
    protected function promoteFromWaitlist(Model $event, int $spotsAvailable): void
    {
        $waitlistRsvps = $event->rsvps()
            ->where('status', 'waitlist')
            ->orderBy('rsvp_at')
            ->get();

        foreach ($waitlistRsvps as $rsvp) {
            if ($spotsAvailable < $rsvp->total_attendees) {
                break; // Not enough spots for this RSVP
            }

            $rsvp->update(['status' => 'going']);
            $spotsAvailable -= $rsvp->total_attendees;

            $rsvp->user->notify(new RsvpPromotedFromWaitlistNotification($event));

            activity()
                ->performedOn($event)
                ->causedBy($rsvp->user)
                ->log('promoted_from_waitlist');
        }
    }

    /**
     * Save question responses
     */
    protected function saveQuestionResponses(Rsvp $rsvp, array $responses): void
    {
        foreach ($responses as $questionId => $response) {
            RsvpResponse::updateOrCreate(
                [
                    'rsvp_id' => $rsvp->id,
                    'rsvp_question_id' => $questionId,
                ],
                [
                    'response' => is_array($response) ? json_encode($response) : $response,
                ]
            );
        }
    }

    /**
     * Record attendance
     */
    public function recordAttendance(
        User $user,
        Model $event,
        bool $attended = true,
        string $method = 'manual',
        ?string $notes = null
    ): Attendance {
        $attendance = Attendance::updateOrCreate(
            [
                'attendable_type' => get_class($event),
                'attendable_id' => $event->id,
                'user_id' => $user->id,
            ],
            [
                'attended' => $attended,
                'checked_in_at' => $attended ? now() : null,
                'check_in_method' => $attended ? $method : null,
                'notes' => $notes,
            ]
        );

        activity()
            ->performedOn($event)
            ->causedBy($user)
            ->withProperties(['attended' => $attended, 'method' => $method])
            ->log('attendance_recorded');

        return $attendance;
    }

    /**
     * Bulk check-in RSVPs
     */
    public function bulkCheckIn(Model $event): int
    {
        $count = 0;

        $event->confirmedRsvps()->each(function ($rsvp) use ($event, &$count) {
            $this->recordAttendance($rsvp->user, $event, true, 'manual');
            $count++;
        });

        return $count;
    }

    /**
     * Send invitations
     */
    public function sendInvitation(
        Model $event,
        User $inviter,
        $recipient, // User or email string
        ?Carbon $expiresAt = null
    ): EventInvitation {
        $invitation = EventInvitation::create([
            'invitable_type' => get_class($event),
            'invitable_id' => $event->id,
            'user_id' => $recipient instanceof User ? $recipient->id : null,
            'email' => $recipient instanceof User ? $recipient->email : $recipient,
            'invited_by' => $inviter->id,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'sent_at' => now(),
        ]);

        // Send invitation email
        if ($recipient instanceof User) {
            $recipient->notify(new EventInvitationNotification($event, $invitation));
        } else {
            // Send to email address (guest invitation)
            Mail::to($recipient)->send(new EventInvitationMail($event, $invitation));
        }

        return $invitation;
    }

    /**
     * Get RSVP statistics for an event
     */
    public function getStatistics(Model $event): array
    {
        $rsvps = $event->rsvps;

        return [
            'total_rsvps' => $rsvps->count(),
            'going_count' => $rsvps->where('status', 'going')->count(),
            'maybe_count' => $rsvps->where('status', 'maybe')->count(),
            'not_going_count' => $rsvps->where('status', 'not_going')->count(),
            'waitlist_count' => $rsvps->where('status', 'waitlist')->count(),
            'total_attendees' => $rsvps->where('status', 'going')->sum('total_attendees'),
            'capacity' => $event->eventSettings?->capacity,
            'available_spots' => $event->getAvailableSpots(),
            'attendance_count' => $event->attendances()->where('attended', true)->count(),
        ];
    }

    /**
     * Export RSVPs for an event
     */
    public function exportRsvps(Model $event): Collection
    {
        return $event->rsvps()
            ->with(['user', 'responses.question'])
            ->get()
            ->map(function ($rsvp) {
                $data = [
                    'name' => $rsvp->user->name,
                    'email' => $rsvp->user->email,
                    'status' => $rsvp->status,
                    'plus_ones' => $rsvp->plus_ones ?? 0,
                    'total_attendees' => $rsvp->total_attendees,
                    'rsvp_date' => $rsvp->rsvp_at->format('Y-m-d H:i:s'),
                    'notes' => $rsvp->notes,
                ];

                // Add custom question responses
                foreach ($rsvp->responses as $response) {
                    $data[$response->question->question] = $response->response;
                }

                return $data;
            });
    }
}
```

## Implementation in Existing Models

### Production
```php
class Production extends Model
{
    use HasRsvps;

    public function getEventTitle(): string
    {
        return $this->title;
    }

    public function getEventStartDateTime(): Carbon
    {
        return $this->date->setTimeFrom($this->doors_time ?? $this->start_time);
    }

    public function getEventEndDateTime(): Carbon
    {
        return $this->date->setTimeFrom($this->end_time);
    }

    public function getEventLocation(): ?string
    {
        return $this->venue ?? 'Corvallis Music Collective';
    }
}
```

### ProgramSession (from Community Programs)
```php
class ProgramSession extends Model
{
    use HasRsvps;

    public function getEventTitle(): string
    {
        return $this->title;
    }

    public function getEventStartDateTime(): Carbon
    {
        return $this->session_date->setTimeFrom($this->start_time);
    }

    public function getEventEndDateTime(): Carbon
    {
        return $this->session_date->setTimeFrom($this->end_time);
    }

    public function getEventLocation(): ?string
    {
        return $this->location;
    }
}
```

### CommunityEvent (new model)
```php
class CommunityEvent extends Model
{
    use SoftDeletes, LogsActivity, HasSlug, HasTags, InteractsWithMedia, HasRsvps;

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function getEventTitle(): string
    {
        return $this->title;
    }

    public function getEventStartDateTime(): Carbon
    {
        return $this->event_date->setTimeFrom($this->start_time);
    }

    public function getEventEndDateTime(): Carbon
    {
        return $this->event_date->setTimeFrom($this->end_time);
    }

    public function getEventLocation(): ?string
    {
        return $this->location;
    }
}
```

## Filament Components

### RsvpFormComponent

Reusable Filament form component for RSVP actions:

```php
use Filament\Forms\Components\Component;

class RsvpFormComponent extends Component
{
    protected string $view = 'filament.forms.components.rsvp-form';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getState(): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        return [
            'rsvp' => $record->getRsvpForUser($user),
            'settings' => $record->eventSettings,
            'stats' => app(RsvpService::class)->getStatistics($record),
        ];
    }
}
```

### RsvpButtonAction

Filament table action for quick RSVP:

```php
use Filament\Tables\Actions\Action;

class RsvpButtonAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'rsvp')
            ->label(fn($record) => $record->hasRsvp(auth()->user()) ? 'Update RSVP' : 'RSVP')
            ->icon('heroicon-o-hand-raised')
            ->color(fn($record) => $record->hasRsvp(auth()->user()) ? 'success' : 'primary')
            ->form(function ($record) {
                $settings = $record->eventSettings;
                $fields = [
                    Select::make('status')
                        ->label('Your Response')
                        ->options([
                            'going' => 'Going',
                            'maybe' => 'Maybe',
                            'not_going' => 'Not Going',
                        ])
                        ->required()
                        ->default(fn() => $record->getRsvpForUser(auth()->user())?->status ?? 'going'),
                ];

                if ($settings->allow_plus_ones) {
                    $fields[] = TextInput::make('plus_ones')
                        ->label('Plus Ones')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue($settings->max_plus_ones ?? 10)
                        ->default(0);
                }

                $fields[] = Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Dietary restrictions, special requests, etc.')
                    ->rows(3);

                // Add custom questions
                foreach ($record->rsvpQuestions as $question) {
                    $field = match ($question->field_type) {
                        'textarea' => Textarea::make("question_{$question->id}"),
                        'select' => Select::make("question_{$question->id}")
                            ->options(collect($question->options)->pluck('label', 'value')),
                        'checkbox' => CheckboxList::make("question_{$question->id}")
                            ->options(collect($question->options)->pluck('label', 'value')),
                        'radio' => Radio::make("question_{$question->id}")
                            ->options(collect($question->options)->pluck('label', 'value')),
                        default => TextInput::make("question_{$question->id}"),
                    };

                    $field = $field->label($question->question);

                    if ($question->required) {
                        $field = $field->required();
                    }

                    $fields[] = $field;
                }

                return $fields;
            })
            ->action(function ($record, array $data) {
                $questionResponses = [];
                foreach ($data as $key => $value) {
                    if (str_starts_with($key, 'question_')) {
                        $questionId = str_replace('question_', '', $key);
                        $questionResponses[$questionId] = $value;
                        unset($data[$key]);
                    }
                }

                app(RsvpService::class)->rsvp(
                    auth()->user(),
                    $record,
                    $data['status'],
                    $data['plus_ones'] ?? null,
                    $data['notes'] ?? null,
                    $questionResponses
                );

                Notification::make()
                    ->title('RSVP Updated')
                    ->success()
                    ->send();
            });
    }
}
```

## Unified Calendar View

### CalendarService

```php
class CalendarService
{
    /**
     * Get all events for calendar view
     */
    public function getEvents(?Carbon $start = null, ?Carbon $end = null, ?User $user = null): Collection
    {
        $start = $start ?? now()->startOfMonth();
        $end = $end ?? now()->endOfMonth()->addMonth();

        $events = collect();

        // Productions
        $productions = Production::query()
            ->whereBetween('date', [$start, $end])
            ->whereIn('status', ['published', 'completed'])
            ->get()
            ->map(fn($p) => $this->formatEvent($p, 'production'));

        // Program Sessions
        $sessions = ProgramSession::query()
            ->whereBetween('session_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(fn($s) => $this->formatEvent($s, 'program_session'));

        // Community Events
        $communityEvents = CommunityEvent::query()
            ->whereBetween('event_date', [$start, $end])
            ->get()
            ->map(fn($e) => $this->formatEvent($e, 'community_event'));

        $events = $events
            ->concat($productions)
            ->concat($sessions)
            ->concat($communityEvents)
            ->sortBy('start');

        // Add RSVP status if user provided
        if ($user) {
            $events = $events->map(function ($event) use ($user) {
                $event['user_rsvp'] = $event['model']->getRsvpForUser($user);
                return $event;
            });
        }

        return $events;
    }

    /**
     * Format event for calendar
     */
    protected function formatEvent($model, string $type): array
    {
        return [
            'id' => $model->id,
            'type' => $type,
            'title' => $model->getEventTitle(),
            'start' => $model->getEventStartDateTime(),
            'end' => $model->getEventEndDateTime(),
            'location' => $model->getEventLocation(),
            'url' => $this->getEventUrl($model, $type),
            'color' => $this->getEventColor($type),
            'model' => $model,
            'rsvp_enabled' => $model->eventSettings?->rsvp_enabled ?? false,
            'capacity' => $model->eventSettings?->capacity,
            'available_spots' => $model->getAvailableSpots(),
        ];
    }

    protected function getEventUrl($model, string $type): string
    {
        return match ($type) {
            'production' => route('filament.member.resources.productions.view', $model),
            'program_session' => route('filament.member.resources.program-sessions.view', $model),
            'community_event' => route('filament.member.resources.community-events.view', $model),
            default => '#',
        };
    }

    protected function getEventColor(string $type): string
    {
        return match ($type) {
            'production' => '#ef4444', // red
            'program_session' => '#3b82f6', // blue
            'community_event' => '#10b981', // green
            default => '#6b7280', // gray
        };
    }
}
```

## Filament Pages

### UnifiedCalendarPage

```php
class UnifiedCalendarPage extends Page
{
    protected static string $view = 'filament.pages.unified-calendar';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Calendar';
    protected static ?int $navigationSort = 2;

    public function getEvents(): array
    {
        return app(CalendarService::class)
            ->getEvents(user: auth()->user())
            ->toArray();
    }
}
```

## Commands

### Send RSVP Reminders
```bash
php artisan rsvp:send-reminders [--hours=24]
```
- Runs daily via scheduler
- Sends reminders X hours before event
- Only to confirmed RSVPs

### Auto Check-In from RSVPs
```bash
php artisan rsvp:auto-checkin
```
- Runs after events end
- Automatically marks confirmed RSVPs as attended
- Configurable per event type

## Notifications

### RsvpConfirmedNotification
- Sent when user confirms RSVP
- Includes event details and calendar file

### RsvpWaitlistedNotification
- Sent when user is placed on waitlist
- Explains waitlist process

### RsvpPromotedFromWaitlistNotification
- Sent when moved from waitlist to confirmed
- Includes updated event details

### EventReminderNotification
- Sent 24 hours before event
- Includes RSVP status and location

### EventInvitationNotification
- Sent when invited to event
- Accept/decline actions

## Widgets

### MyUpcomingEventsWidget
```php
class MyUpcomingEventsWidget extends Widget
{
    protected static string $view = 'filament.widgets.my-upcoming-events';

    public function getEvents(): Collection
    {
        return app(CalendarService::class)
            ->getEvents(now(), now()->addWeeks(2), auth()->user())
            ->filter(fn($event) => $event['user_rsvp']?->status === 'going')
            ->take(5);
    }
}
```

### EventRsvpStatsWidget
```php
class EventRsvpStatsWidget extends Widget
{
    protected static string $view = 'filament.widgets.event-rsvp-stats';
    public $event;

    public function getStats(): array
    {
        return app(RsvpService::class)->getStatistics($this->event);
    }
}
```

## Public Pages

### Event Detail Page
- Shows event info with RSVP button (if enabled)
- RSVP list (public or members-only)
- Custom questions during RSVP
- Share event functionality

### My RSVPs Page
- List of all user's RSVPs
- Filter by status (going, maybe, waitlist)
- Quick cancel actions
- Export to calendar

## Migration Strategy

### Phase 1: Create New Tables
- Create unified RSVP tables
- Keep existing RSVP data intact

### Phase 2: Migrate Existing Data
If you have existing RSVP data in `program_rsvps`:

```php
// Migration to move data
ProgramRsvp::all()->each(function ($oldRsvp) {
    Rsvp::create([
        'rsvpable_type' => ProgramSession::class,
        'rsvpable_id' => $oldRsvp->program_session_id,
        'user_id' => $oldRsvp->user_id,
        'status' => $oldRsvp->status,
        'rsvp_at' => $oldRsvp->rsvp_at,
        'notes' => $oldRsvp->notes,
        'reminder_sent_at' => $oldRsvp->reminder_sent_at,
    ]);
});
```

### Phase 3: Update Models
- Add `HasRsvps` trait to Production, ProgramSession, CommunityEvent
- Update relationships

### Phase 4: Update Resources
- Replace model-specific RSVP forms with unified component
- Test all RSVP flows

### Phase 5: Remove Old Tables
- Drop `program_rsvps` and `program_attendance` tables
- Clean up old code

## Integration with Existing Systems

### Practice Space Reservations
- RSVPs do NOT reserve practice space
- Reservations remain separate system
- Calendar shows both

### Volunteer System
- Volunteer shifts can have RSVPs
- Track who's volunteering for which role
- Auto-record volunteer hours from attendance

### Credits System
- Optional: reward practice space credits for event attendance
- Configure per event type

## Permissions

### Abilities
- `view_event_rsvps` - View RSVP list (organizers, admins)
- `manage_event_settings` - Configure RSVP settings
- `record_attendance` - Check people in
- `send_invitations` - Invite people to events
- `export_rsvps` - Export RSVP data

## Implementation Estimates

### Phase 1: Core Tables & Models (8-12 hours)
- Database migrations for unified tables
- Rsvp, Attendance, EventSetting models
- HasRsvps trait implementation
- RsvpService with core methods

### Phase 2: Integration with Existing Models (6-8 hours)
- Add HasRsvps to Production, ProgramSession
- Migrate existing program_rsvps data
- Update relationships and queries

### Phase 3: Filament Components & Actions (10-14 hours)
- RsvpFormComponent and RsvpButtonAction
- Event settings form in resources
- RSVP management interfaces
- Custom question builder

### Phase 4: Calendar & Dashboard (8-10 hours)
- CalendarService implementation
- UnifiedCalendarPage with FullCalendar.js
- MyUpcomingEventsWidget
- EventRsvpStatsWidget

### Phase 5: Notifications & Commands (6-8 hours)
- All RSVP notifications
- Reminder command
- Auto check-in command
- Invitation emails

### Phase 6: Public Pages & UX (8-12 hours)
- Event detail page with RSVP
- My RSVPs page
- iCal export
- Social sharing

### Phase 7: Advanced Features (6-10 hours)
- Custom questions per event
- Invitation system
- Waitlist management
- QR code check-in

### Phase 8: Testing & Polish (6-8 hours)
- Feature tests for all RSVP flows
- Test command
- Documentation
- UI/UX refinements

**Total Estimate: 58-82 hours**

## Future Enhancements

- Mobile app check-in with QR codes
- SMS notifications and reminders
- Social media integration (share RSVPs)
- Collaborative RSVPs (friends can see who's going)
- Event suggestions based on past RSVPs
- RSVP streaks and gamification
- Integration with Eventbrite/Meetup for external events
- Automatic photographer/videographer sign-up
- Post-event surveys and feedback
- Analytics dashboard (popular event types, no-show rates)
