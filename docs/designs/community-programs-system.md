# Community Programs System Design

## Overview

A system to manage recurring community programs like Real Book Club, monthly meetups, jam sessions, workshops, and other regular activities. Supports RSVP tracking, program schedules, session notes, attendance, and integration with the practice space reservation system.

## Core Features

### 1. Program Management
- Define recurring programs (Real Book Club, Monthly Meetup, Open Mic, etc.)
- Set meeting schedules using RRULE patterns (weekly, bi-weekly, monthly)
- Assign facilitators and hosts
- Track program visibility (public, members-only, invite-only)
- Program categories and tags for organization

### 2. Session Management
- Auto-generate sessions based on program schedule
- Session-specific details (topic, repertoire, materials)
- RSVP tracking with optional capacity limits
- Attendance recording
- Session notes and resources
- Session cancellation and rescheduling

### 3. Real Book Club Features
- Track song selections per session
- Share charts and recordings
- Voting on future songs
- Repertoire history and favorites
- Skill level recommendations

### 4. Jam Session Features
- Key/tempo suggestions
- Song rotation lists
- Instrument signup (who's bringing what)
- Gear sharing coordination
- Video/audio recording permissions

### 5. Communication
- Session reminders via email
- Program announcements
- Discussion threads per session
- Materials sharing (PDFs, links, videos)

### 6. Engagement Tracking
- Attendance streaks and milestones
- Active participant recognition
- Program feedback and ratings
- Session highlights and photos

## Database Schema

### Tables

#### `community_programs`
```
id - bigint primary key
name - string
slug - string unique
description - text
program_type - enum (book_club, jam_session, meetup, workshop, open_mic, other)
visibility - enum (public, members_only, invite_only)
status - enum (active, paused, archived)
recurrence_rule - text (RRULE format)
default_location - string nullable
default_duration_minutes - integer
capacity - integer nullable
facilitator_user_id - foreign key to users nullable
auto_generate_sessions - boolean (default true)
generation_days_ahead - integer (default 90)
settings - json (program-specific settings)
created_by - foreign key to users
created_at, updated_at, deleted_at
```

#### `program_sessions`
```
id - bigint primary key
community_program_id - foreign key
title - string
description - text nullable
session_date - date
start_time - time
end_time - time
location - string nullable
capacity - integer nullable (overrides program default)
status - enum (scheduled, in_progress, completed, cancelled)
facilitator_user_id - foreign key to users nullable
rsvp_required - boolean (default false)
rsvp_deadline - timestamp nullable
notes - text nullable (session notes/recap)
materials - json (links, files, resources)
metadata - json (program-specific data: songs, topics, etc.)
cancelled_at - timestamp nullable
cancellation_reason - text nullable
created_at, updated_at, deleted_at
```

#### `program_rsvps`
```
id - bigint primary key
program_session_id - foreign key
user_id - foreign key
status - enum (going, maybe, not_going, waitlist)
rsvp_at - timestamp
updated_at - timestamp
notes - text nullable (dietary restrictions, +1 count, etc.)
reminder_sent_at - timestamp nullable
unique (program_session_id, user_id)
```

#### `program_attendance`
```
id - bigint primary key
program_session_id - foreign key
user_id - foreign key
attended - boolean
checked_in_at - timestamp nullable
notes - text nullable
created_at, updated_at
unique (program_session_id, user_id)
```

#### `program_repertoire`
```
id - bigint primary key
community_program_id - foreign key
title - string (song name, topic, etc.)
composer_artist - string nullable
key - string nullable (for music)
tempo - string nullable
difficulty_level - enum (beginner, intermediate, advanced) nullable
notes - text nullable
external_links - json (YouTube, lead sheets, etc.)
times_played - integer (default 0)
last_played_at - timestamp nullable
created_by - foreign key to users
created_at, updated_at, deleted_at
```

#### `program_session_repertoire`
```
id - bigint primary key
program_session_id - foreign key
program_repertoire_id - foreign key
position - integer (order in setlist)
notes - text nullable (session-specific notes)
created_at, updated_at
```

#### `program_votes`
```
id - bigint primary key
votable_type - string (ProgramRepertoire, etc.)
votable_id - bigint
community_program_id - foreign key
user_id - foreign key
vote_type - enum (up, down, neutral)
created_at, updated_at
unique (votable_type, votable_id, user_id)
```

#### `program_milestones`
```
id - bigint primary key
name - string
description - text
sessions_required - integer
badge_icon - string nullable
is_active - boolean
created_at, updated_at
```

#### `program_milestone_achievements`
```
id - bigint primary key
user_id - foreign key
program_milestone_id - foreign key
community_program_id - foreign key
achieved_at - timestamp
sessions_attended - integer
created_at, updated_at
```

#### `program_discussions`
```
id - bigint primary key
program_session_id - foreign key
user_id - foreign key
parent_id - foreign key to program_discussions nullable (for threading)
content - text
created_at, updated_at, deleted_at
```

## Models & Relationships

### CommunityProgram
```php
class CommunityProgram extends Model
{
    use SoftDeletes, LogsActivity, HasSlug, HasTags, InteractsWithMedia;

    protected $casts = [
        'auto_generate_sessions' => 'boolean',
        'generation_days_ahead' => 'integer',
        'capacity' => 'integer',
        'default_duration_minutes' => 'integer',
        'settings' => 'array',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function sessions()
    {
        return $this->hasMany(ProgramSession::class);
    }

    public function upcomingSessions()
    {
        return $this->sessions()
            ->where('session_date', '>=', today())
            ->where('status', '!=', 'cancelled')
            ->orderBy('session_date')
            ->orderBy('start_time');
    }

    public function facilitator()
    {
        return $this->belongsTo(User::class, 'facilitator_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function repertoire()
    {
        return $this->hasMany(ProgramRepertoire::class);
    }

    public function milestoneAchievements()
    {
        return $this->hasMany(ProgramMilestoneAchievement::class);
    }

    /**
     * Get RRULE instance for parsing recurrence
     */
    public function getRRule(): ?\RRule\RRule
    {
        if (!$this->recurrence_rule) {
            return null;
        }

        return new \RRule\RRule($this->recurrence_rule);
    }

    /**
     * Check if program is music-focused
     */
    public function isMusicalProgram(): bool
    {
        return in_array($this->program_type, ['book_club', 'jam_session', 'open_mic']);
    }
}
```

### ProgramSession
```php
class ProgramSession extends Model
{
    use SoftDeletes, LogsActivity, InteractsWithMedia;

    protected $casts = [
        'session_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'rsvp_required' => 'boolean',
        'rsvp_deadline' => 'datetime',
        'cancelled_at' => 'datetime',
        'capacity' => 'integer',
        'materials' => 'array',
        'metadata' => 'array',
    ];

    public function program()
    {
        return $this->belongsTo(CommunityProgram::class, 'community_program_id');
    }

    public function facilitator()
    {
        return $this->belongsTo(User::class, 'facilitator_user_id');
    }

    public function rsvps()
    {
        return $this->hasMany(ProgramRsvp::class);
    }

    public function attendees()
    {
        return $this->hasMany(ProgramAttendance::class)->where('attended', true);
    }

    public function repertoire()
    {
        return $this->belongsToMany(
            ProgramRepertoire::class,
            'program_session_repertoire',
            'program_session_id',
            'program_repertoire_id'
        )
            ->withPivot('position', 'notes')
            ->orderBy('position');
    }

    public function discussions()
    {
        return $this->hasMany(ProgramDiscussion::class)
            ->whereNull('parent_id')
            ->orderBy('created_at');
    }

    /**
     * Get count of confirmed RSVPs
     */
    public function getConfirmedRsvpCount(): int
    {
        return $this->rsvps()->where('status', 'going')->count();
    }

    /**
     * Check if session has available spots
     */
    public function hasAvailableSpots(): bool
    {
        if (!$this->capacity) {
            return true;
        }

        return $this->getConfirmedRsvpCount() < $this->capacity;
    }

    /**
     * Check if session is in the past
     */
    public function isPast(): bool
    {
        $sessionDateTime = $this->session_date->setTimeFrom($this->end_time);
        return $sessionDateTime->isPast();
    }

    /**
     * Get full datetime for session start
     */
    public function getStartDateTime(): Carbon
    {
        return $this->session_date->setTimeFrom($this->start_time);
    }

    /**
     * Get full datetime for session end
     */
    public function getEndDateTime(): Carbon
    {
        return $this->session_date->setTimeFrom($this->end_time);
    }
}
```

### ProgramRsvp
```php
class ProgramRsvp extends Model
{
    protected $casts = [
        'rsvp_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ProgramSession::class, 'program_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user is on waitlist
     */
    public function isWaitlisted(): bool
    {
        return $this->status === 'waitlist';
    }
}
```

### ProgramAttendance
```php
class ProgramAttendance extends Model
{
    protected $casts = [
        'attended' => 'boolean',
        'checked_in_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ProgramSession::class, 'program_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### ProgramRepertoire
```php
class ProgramRepertoire extends Model
{
    use SoftDeletes, LogsActivity;

    protected $casts = [
        'times_played' => 'integer',
        'last_played_at' => 'datetime',
        'external_links' => 'array',
    ];

    public function program()
    {
        return $this->belongsTo(CommunityProgram::class, 'community_program_id');
    }

    public function sessions()
    {
        return $this->belongsToMany(
            ProgramSession::class,
            'program_session_repertoire',
            'program_repertoire_id',
            'program_session_id'
        )
            ->withPivot('position', 'notes')
            ->orderBy('session_date', 'desc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function votes()
    {
        return $this->morphMany(ProgramVote::class, 'votable');
    }

    /**
     * Get vote score
     */
    public function getVoteScore(): int
    {
        return $this->votes()->sum(DB::raw("CASE WHEN vote_type = 'up' THEN 1 WHEN vote_type = 'down' THEN -1 ELSE 0 END"));
    }
}
```

### ProgramVote
```php
class ProgramVote extends Model
{
    public function votable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(CommunityProgram::class, 'community_program_id');
    }
}
```

### ProgramMilestone
```php
class ProgramMilestone extends Model
{
    protected $casts = [
        'sessions_required' => 'integer',
        'is_active' => 'boolean',
    ];

    public function achievements()
    {
        return $this->hasMany(ProgramMilestoneAchievement::class);
    }
}
```

### ProgramMilestoneAchievement
```php
class ProgramMilestoneAchievement extends Model
{
    protected $casts = [
        'achieved_at' => 'datetime',
        'sessions_attended' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function milestone()
    {
        return $this->belongsTo(ProgramMilestone::class);
    }

    public function program()
    {
        return $this->belongsTo(CommunityProgram::class, 'community_program_id');
    }
}
```

### ProgramDiscussion
```php
class ProgramDiscussion extends Model
{
    use SoftDeletes;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ProgramSession::class, 'program_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ProgramDiscussion::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ProgramDiscussion::class, 'parent_id')
            ->orderBy('created_at');
    }
}
```

## Service Layer

### CommunityProgramService

```php
class CommunityProgramService
{
    /**
     * Generate future sessions based on program recurrence rule
     */
    public function generateSessions(CommunityProgram $program, ?Carbon $until = null): Collection
    {
        if (!$program->auto_generate_sessions || !$program->recurrence_rule) {
            return collect();
        }

        $until = $until ?? now()->addDays($program->generation_days_ahead);
        $rrule = $program->getRRule();

        if (!$rrule) {
            return collect();
        }

        // Get existing session dates to avoid duplicates
        $existingDates = $program->sessions()
            ->where('session_date', '>=', today())
            ->pluck('session_date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $generatedSessions = collect();

        foreach ($rrule as $occurrence) {
            $occurrenceDate = Carbon::instance($occurrence);

            if ($occurrenceDate->isAfter($until)) {
                break;
            }

            if ($occurrenceDate->isBefore(today())) {
                continue;
            }

            $dateString = $occurrenceDate->format('Y-m-d');
            if (in_array($dateString, $existingDates)) {
                continue;
            }

            $session = $this->createSessionFromProgram($program, $occurrenceDate);
            $generatedSessions->push($session);
            $existingDates[] = $dateString;
        }

        return $generatedSessions;
    }

    /**
     * Create a session instance from program template
     */
    protected function createSessionFromProgram(CommunityProgram $program, Carbon $date): ProgramSession
    {
        $startTime = $program->settings['default_start_time'] ?? '19:00';
        $duration = $program->default_duration_minutes ?? 120;

        $start = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
        $end = $start->copy()->addMinutes($duration);

        return ProgramSession::create([
            'community_program_id' => $program->id,
            'title' => $program->name,
            'session_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'location' => $program->default_location,
            'capacity' => $program->capacity,
            'facilitator_user_id' => $program->facilitator_user_id,
            'rsvp_required' => $program->settings['rsvp_required'] ?? false,
            'status' => 'scheduled',
        ]);
    }

    /**
     * RSVP for a session
     */
    public function rsvpForSession(User $user, ProgramSession $session, string $status = 'going', ?string $notes = null): ProgramRsvp
    {
        // Check capacity
        if ($status === 'going' && !$session->hasAvailableSpots()) {
            $status = 'waitlist';
        }

        return ProgramRsvp::updateOrCreate(
            [
                'program_session_id' => $session->id,
                'user_id' => $user->id,
            ],
            [
                'status' => $status,
                'rsvp_at' => now(),
                'notes' => $notes,
            ]
        );
    }

    /**
     * Cancel RSVP and promote waitlist
     */
    public function cancelRsvp(ProgramRsvp $rsvp): void
    {
        $session = $rsvp->session;
        $wasGoing = $rsvp->status === 'going';

        $rsvp->update(['status' => 'not_going']);

        // Promote first person on waitlist
        if ($wasGoing && $session->capacity) {
            $waitlistRsvp = $session->rsvps()
                ->where('status', 'waitlist')
                ->orderBy('rsvp_at')
                ->first();

            if ($waitlistRsvp) {
                $waitlistRsvp->update(['status' => 'going']);
                $waitlistRsvp->user->notify(new ProgramWaitlistPromotedNotification($session));
            }
        }
    }

    /**
     * Record attendance for a session
     */
    public function recordAttendance(ProgramSession $session, User $user, bool $attended = true, ?string $notes = null): ProgramAttendance
    {
        $attendance = ProgramAttendance::updateOrCreate(
            [
                'program_session_id' => $session->id,
                'user_id' => $user->id,
            ],
            [
                'attended' => $attended,
                'checked_in_at' => $attended ? now() : null,
                'notes' => $notes,
            ]
        );

        if ($attended) {
            $this->checkMilestones($user, $session->program);
        }

        return $attendance;
    }

    /**
     * Get user's attendance count for a program
     */
    public function getUserAttendanceCount(User $user, CommunityProgram $program): int
    {
        return ProgramAttendance::where('user_id', $user->id)
            ->where('attended', true)
            ->whereHas('session', function ($query) use ($program) {
                $query->where('community_program_id', $program->id);
            })
            ->count();
    }

    /**
     * Check and award milestones
     */
    protected function checkMilestones(User $user, CommunityProgram $program): void
    {
        $attendanceCount = $this->getUserAttendanceCount($user, $program);

        $milestones = ProgramMilestone::where('is_active', true)
            ->where('sessions_required', '<=', $attendanceCount)
            ->whereDoesntHave('achievements', function ($query) use ($user, $program) {
                $query->where('user_id', $user->id)
                    ->where('community_program_id', $program->id);
            })
            ->get();

        foreach ($milestones as $milestone) {
            ProgramMilestoneAchievement::create([
                'user_id' => $user->id,
                'program_milestone_id' => $milestone->id,
                'community_program_id' => $program->id,
                'achieved_at' => now(),
                'sessions_attended' => $attendanceCount,
            ]);

            $user->notify(new ProgramMilestoneAchievedNotification($milestone, $program));
        }
    }

    /**
     * Add repertoire to session
     */
    public function addRepertoireToSession(ProgramSession $session, ProgramRepertoire $repertoire, int $position, ?string $notes = null): void
    {
        $session->repertoire()->attach($repertoire->id, [
            'position' => $position,
            'notes' => $notes,
        ]);

        $repertoire->increment('times_played');
        $repertoire->update(['last_played_at' => now()]);
    }

    /**
     * Vote on repertoire
     */
    public function voteRepertoire(User $user, ProgramRepertoire $repertoire, string $voteType): ProgramVote
    {
        return ProgramVote::updateOrCreate(
            [
                'votable_type' => ProgramRepertoire::class,
                'votable_id' => $repertoire->id,
                'user_id' => $user->id,
            ],
            [
                'vote_type' => $voteType,
                'community_program_id' => $repertoire->community_program_id,
            ]
        );
    }

    /**
     * Get top voted repertoire for a program
     */
    public function getTopVotedRepertoire(CommunityProgram $program, int $limit = 10): Collection
    {
        return $program->repertoire()
            ->withCount([
                'votes as vote_score' => function ($query) {
                    $query->select(DB::raw("SUM(CASE WHEN vote_type = 'up' THEN 1 WHEN vote_type = 'down' THEN -1 ELSE 0 END)"));
                }
            ])
            ->orderByDesc('vote_score')
            ->limit($limit)
            ->get();
    }

    /**
     * Cancel a session
     */
    public function cancelSession(ProgramSession $session, string $reason): void
    {
        $session->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // Notify all RSVPs
        $session->rsvps()->whereIn('status', ['going', 'maybe', 'waitlist'])->each(function ($rsvp) use ($session, $reason) {
            $rsvp->user->notify(new ProgramSessionCancelledNotification($session, $reason));
        });
    }

    /**
     * Reschedule a session
     */
    public function rescheduleSession(ProgramSession $session, Carbon $newDate, Carbon $newStartTime, Carbon $newEndTime): void
    {
        $oldDate = $session->session_date;

        $session->update([
            'session_date' => $newDate,
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
        ]);

        // Notify all RSVPs
        $session->rsvps()->whereIn('status', ['going', 'maybe', 'waitlist'])->each(function ($rsvp) use ($session, $oldDate) {
            $rsvp->user->notify(new ProgramSessionRescheduledNotification($session, $oldDate));
        });
    }
}
```

## Filament Resources

### CommunityProgramResource
- Location: `/member/community-programs`
- Public listing of active programs
- Admin create/edit with RRULE builder
- Program settings per type (book club, jam session, etc.)
- Statistics: total sessions, average attendance, active participants

### ProgramSessionResource
- Location: `/member/program-sessions`
- Calendar view with program color coding
- RSVP management and waitlist
- Session notes and materials upload
- Attendance check-in interface
- Discussion threads

### ProgramRepertoireResource (Book Club specific)
- Location: `/member/programs/{program}/repertoire`
- Song/topic voting interface
- Repertoire history and statistics
- External resource links
- Difficulty ratings

### ProgramAttendanceResource
- Admin-only view for tracking
- Export functionality
- Milestone progress tracking
- Attendance trends

## Commands

### Generate Program Sessions
```bash
php artisan programs:generate-sessions [--program-id=] [--days=90]
```
- Runs daily via scheduler
- Generates sessions for all active programs
- Configurable lookahead period

### Send Session Reminders
```bash
php artisan programs:send-reminders
```
- Runs daily via scheduler
- Sends reminders 24 hours before sessions
- Only to RSVPs with status 'going' or 'maybe'

### Complete Past Sessions
```bash
php artisan programs:complete-sessions
```
- Runs daily via scheduler
- Auto-completes sessions after end time
- Updates statistics

### Test Community Programs
```bash
php artisan test:community-programs [--clean] [--dry-run]
```
- Create test programs with different types
- Generate sessions and RSVPs
- Test voting and attendance
- Cleanup with --clean

## Notifications

### ProgramSessionReminderNotification
- Sent 24 hours before session
- Includes session details, location, repertoire
- Quick RSVP change actions

### ProgramSessionCancelledNotification
- Sent when session is cancelled
- Includes reason and alternative dates

### ProgramSessionRescheduledNotification
- Sent when session is rescheduled
- Shows old and new date/time
- Re-confirm RSVP action

### ProgramWaitlistPromotedNotification
- Sent when moved from waitlist to confirmed
- Includes session details

### ProgramMilestoneAchievedNotification
- Sent when attendance milestone reached
- Shows badge and encourages continued participation

### ProgramNewSessionNotification
- Optional: sent when new sessions are generated
- Weekly digest format

## Widget Ideas

### Dashboard Widgets

#### UpcomingProgramSessionsWidget
- Shows next 3-5 sessions across all programs
- Quick RSVP actions
- Location and time info

#### MyProgramsWidget
- Programs user frequently attends
- Attendance stats and streaks
- Next session per program

#### RepertoireVotingWidget (Book Club)
- Top voted songs for next session
- Quick vote interface
- Recently played songs

## Public Pages

### Program Listing Page
- Public view at `/programs`
- Shows all public and member programs (if logged in)
- Filter by type, day of week
- Calendar view option

### Program Detail Page
- Public view at `/programs/{slug}`
- Program description and schedule
- Upcoming sessions with RSVP (if member)
- Past session highlights
- Facilitator info

### Session Detail Page
- At `/programs/{program}/sessions/{session}`
- Session-specific info and materials
- RSVP and attendance list
- Discussion thread
- Photos/recordings if available

## Integration Points

### Practice Space Reservations
- Check for program sessions when booking practice space
- Optional: auto-reserve space for programs
- Conflict detection and warnings

### Member Profiles
- Show programs user participates in
- Display attendance badges
- Link to contributed repertoire

### Calendar Integration
- Export program sessions to iCal
- Google Calendar sync
- Unified calendar view with reservations and productions

## Permissions

### Roles & Abilities
- `view_programs` - Public and members
- `manage_programs` - Admins, program facilitators
- `manage_own_programs` - Facilitators for their programs only
- `view_attendance` - Program facilitators and admins
- `record_attendance` - Program facilitators and admins
- `manage_repertoire` - All members can add, facilitators can edit/delete
- `vote_repertoire` - All members

## Real Book Club Specific Features

### Song Selection Workflow
1. Members suggest songs and vote
2. Facilitator reviews top voted songs
3. Facilitator selects songs for next session (usually 3-5)
4. Members receive song list 1 week before session
5. Session focuses on selected songs
6. Post-session notes and recordings shared

### Metadata Schema for Real Book Club
```json
{
  "session_theme": "Bebop Standards",
  "difficulty_range": "intermediate-advanced",
  "recommended_prep": "Focus on changes and melody",
  "recording_links": ["https://..."],
  "charts_available": true
}
```

### Repertoire External Links
```json
{
  "youtube_performances": ["https://youtube.com/..."],
  "lead_sheet_url": "https://...",
  "play_along_track": "https://...",
  "analysis_article": "https://..."
}
```

## Jam Session Specific Features

### Instrument Tracking
- Add to session RSVP notes
- "Bringing: bass, amp"
- "Looking for: drummer, keys"
- Helps coordinate gear sharing

### Song Queue Management
- Real-time queue during session
- Mark songs as played
- Track who called each song

### Metadata Schema for Jam Sessions
```json
{
  "session_vibe": "blues_rock",
  "skill_level": "all_welcome",
  "gear_available": ["PA system", "drum kit", "bass amp"],
  "bring_your_own": ["guitar", "bass", "sticks"],
  "recording_allowed": true
}
```

## Monthly Meetup Features

### Agenda Building
- Collaborative agenda suggestions
- Vote on discussion topics
- Time allocation per topic

### Meeting Notes
- Shared note-taking interface
- Action items tracking
- Decision documentation

### Metadata Schema for Meetups
```json
{
  "agenda_items": [
    {"topic": "Space renovations", "time_minutes": 15},
    {"topic": "Budget review", "time_minutes": 20}
  ],
  "action_items": [
    {"task": "Get quotes", "assigned_to": "user_id", "due_date": "2025-11-01"}
  ]
}
```

## Implementation Estimates

### Phase 1: Core Models & Basic Functionality (12-16 hours)
- Database migrations for all tables
- Model definitions with relationships
- Basic CommunityProgramService methods
- Session generation from RRULE
- Seeders with sample programs

### Phase 2: Filament Resources & RSVP (14-18 hours)
- CommunityProgramResource with RRULE builder
- ProgramSessionResource with calendar view
- RSVP and waitlist management
- Attendance recording interface
- Discussion threads

### Phase 3: Repertoire & Voting (8-12 hours)
- ProgramRepertoireResource
- Voting system implementation
- Session repertoire selection
- External links and materials

### Phase 4: Automation & Integration (8-10 hours)
- Scheduled commands (generate, remind, complete)
- Notifications
- Integration with practice space system
- Public pages

### Phase 5: Program-Specific Features (6-10 hours)
- Real Book Club features
- Jam session features
- Meetup features
- Milestones and badges

### Phase 6: Testing & Polish (6-8 hours)
- Test command implementation
- Feature tests for each program type
- UI/UX refinements
- Documentation

**Total Estimate: 54-74 hours**

## Future Enhancements

### Advanced Features
- Mobile app with check-in QR codes
- Video conferencing integration (Zoom/Meet)
- Live streaming for remote participants
- Automated setlist generation from votes
- Practice tracking (hours spent on repertoire)
- Skill progression tracking
- Inter-program challenges and collaborations
- Automated session recordings upload
- AI-generated session summaries
- Translation for multilingual programs
- Accessibility features (ASL, captions)

### Analytics
- Attendance patterns and trends
- Repertoire popularity over time
- Member engagement scoring
- Retention and churn analysis
- Program health dashboard

### Gamification
- Attendance streaks with rewards
- Program challenges and competitions
- Social sharing of achievements
- Leaderboards (most active, most votes, etc.)
- Collaborative goals (e.g., "Learn 50 songs as a group")
