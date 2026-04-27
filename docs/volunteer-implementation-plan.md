# Volunteer Management — Implementation Plan

Sequenced for a solo developer working through it PR by PR. Each epic groups related work; each task within an epic is roughly one pull request. Tasks within an epic are ordered; epics are ordered by dependency (later epics depend on earlier ones unless noted).

---

## Epic 1: Module scaffold, models, and migrations

Foundation. Everything else builds on these tables and classes.

### 1.1 Scaffold the volunteering module

Create `app-modules/volunteering/` following the existing modular pattern:

```
app-modules/volunteering/
├── composer.json
├── database/
│   ├── factories/
│   └── migrations/
├── src/
│   ├── Concerns/
│   ├── Events/
│   ├── Exceptions/
│   ├── Models/
│   ├── Providers/
│   │   └── VolunteeringServiceProvider.php
│   ├── Services/
│   └── States/
└── tests/
```

Register the service provider. Verify the module loads and the test suite still passes.

**Test:** Module boots, service provider registers, no regressions.

### 1.2 Create Position model and migration

New table `volunteer_positions`:

```
id, title (string), description (text nullable), timestamps, soft_deletes
```

Model at `app-modules/volunteering/src/Models/Position.php`. Add `HasTags` trait from `spatie/laravel-tags`. Add `SoftDeletes` trait.

Factory at `app-modules/volunteering/database/factories/PositionFactory.php`.

**Test (unit):** Position creation, tagging, soft delete hides from queries but preserves record.

### 1.3 Create Shift model and migration

New table `volunteer_shifts`:

```
id, position_id (FK), event_id (int nullable FK to events.id),
start_at (timestamp), end_at (timestamp), capacity (integer),
timestamps
```

Model at `app-modules/volunteering/src/Models/Shift.php`. Add `HasTags` trait. Scopes: `forEvent($eventId)`, `upcoming()`, `withAvailableCapacity()`. Indexes on `(event_id)`, `(position_id)`, `(start_at)`.

Factory at `app-modules/volunteering/database/factories/ShiftFactory.php`.

**Test (unit):** Shift creation, scopes return correct results, tagging.

### 1.4 Create HourLog model, migration, and state machine

New table `volunteer_hour_logs`:

```
id, user_id (FK), shift_id (int nullable FK), position_id (int nullable FK),
status (HourLogState), started_at (timestamp nullable),
ended_at (timestamp nullable),
reviewed_by (int nullable FK), notes (text nullable),
timestamps
```

Model at `app-modules/volunteering/src/Models/HourLog.php`. Add `HasTags` trait. Computed `minutes` accessor using `diffInMinutes` from `started_at` to `ended_at` (not a stored column — avoids PostgreSQL/SQLite dialect issues).

Check constraint: exactly one of `shift_id` or `position_id` must be non-null.

Partial unique index on `(user_id, shift_id)` excluding `released` and `checked_out` statuses.

Indexes on `(shift_id, status)`, `(user_id, status)`, `(status)`.

State classes under `app-modules/volunteering/src/States/HourLogState/`:

**Shift lifecycle:** `Interested`, `Confirmed`, `Released`, `CheckedIn`, `CheckedOut`

**Self-reported lifecycle:** `Pending`, `Approved`, `Rejected`

Base class at `app-modules/volunteering/src/States/HourLogState.php` with allowed transitions:
- `Interested → Confirmed`, `Interested → Released`
- `Confirmed → CheckedIn`, `Confirmed → Released`
- `CheckedIn → CheckedOut`, `CheckedIn → Released`
- `Pending → Approved`, `Pending → Rejected`

Terminal states: `Released`, `CheckedOut`, `Approved`, `Rejected`.

Each state class gets `$name`, `getColor()`, `getIcon()`, `getLabel()` following the `TicketState` pattern.

Factory at `app-modules/volunteering/database/factories/HourLogFactory.php`.

**Test (unit):** All valid transitions succeed, all invalid transitions are rejected. `minutes` accessor returns correct value. Check constraint rejects both-null and both-set. Unique constraint prevents double-signup.

### 1.5 Create domain events

All under `app-modules/volunteering/src/Events/`:

- `VolunteerConfirmed` — carries HourLog. Fired on `Interested → Confirmed`.
- `VolunteerReleased` — carries HourLog. Fired on transition to `Released`.
- `VolunteerCheckedIn` — carries HourLog. Fired on `Confirmed → CheckedIn`.
- `VolunteerCheckedOut` — carries HourLog. Fired on `CheckedIn → CheckedOut`.
- `HoursSubmitted` — carries HourLog. Fired on self-reported HourLog creation.
- `HoursApproved` — carries HourLog. Fired on `Pending → Approved`.

**Test:** Events are dispatchable, carry the correct payload.

### 1.6 Wire relationships in integration layer

In `AppServiceProvider::boot()`:

- `Shift::resolveRelationUsing('event', ...)` — `belongsTo(Event::class, 'event_id')`.
- `User::resolveRelationUsing('volunteerHourLogs', ...)` — `hasMany(HourLog::class)`.

**Test (integration):** `$shift->event` resolves correctly. `$user->volunteerHourLogs` returns the user's hour logs.

---

## Epic 2: Services

The business logic layer. Each service is a singleton registered in `VolunteeringServiceProvider`, following the pattern established by `EventService`.

### 2.1 PositionService

`app-modules/volunteering/src/Services/PositionService.php`

Methods:
- `create(array $data): Position` — validates title required, description optional. Creates and returns.
- `update(Position $position, array $data): Position` — updates title and/or description.
- `delete(Position $position): void` — soft-deletes.

Register as singleton in `VolunteeringServiceProvider`.

**Test (feature):** Create, update, soft-delete. Soft-deleted position not returned by default queries.

### 2.2 ShiftService

`app-modules/volunteering/src/Services/ShiftService.php`

Methods:
- `create(array $data): Shift` — validates `position_id` exists (and not soft-deleted), `start_at` < `end_at`, `capacity` >= 1, `event_id` optional and exists if provided. Wraps in DB transaction.
- `update(Shift $shift, array $data): Shift` — updates fields.
- `delete(Shift $shift): void` — deletes (hard delete, no hour logs should reference a shift that never had sign-ups; if it does, FK constraint protects).

**Test (feature):** Create with and without event. Validation rejects bad data. Update works.

### 2.3 HourLogService

`app-modules/volunteering/src/Services/HourLogService.php`

The core of the module. All methods wrap in DB transactions and fire domain events.

Methods:

**Shift lifecycle:**
- `signUp(User $user, Shift $shift): HourLog` — checks shift is in the future, capacity not full (non-Released/non-CheckedOut count < capacity), user doesn't already have an active HourLog for this shift. Creates in `Interested` status.
- `confirm(HourLog $hourLog, User $reviewer): HourLog` — transitions `Interested → Confirmed`, sets `reviewed_by`. Fires `VolunteerConfirmed`.
- `release(HourLog $hourLog, User $reviewer): HourLog` — transitions to `Released` from `Interested`, `Confirmed`, or `CheckedIn`. Sets `reviewed_by`. Fires `VolunteerReleased`.
- `checkIn(HourLog $hourLog): HourLog` — transitions `Confirmed → CheckedIn`, sets `started_at` to now. Fires `VolunteerCheckedIn`.
- `walkIn(User $user, Shift $shift): HourLog` — creates HourLog in `CheckedIn` directly, sets `started_at` to now. Capacity check applies but can be overridden.
- `checkOut(HourLog $hourLog): HourLog` — transitions `CheckedIn → CheckedOut`, sets `ended_at` to now. Propagates tags from Shift and Shift's Position onto HourLog (additive via `attachTags`). Fires `VolunteerCheckedOut`.

**Self-reported lifecycle:**
- `submitHours(User $user, array $data): HourLog` — validates `position_id` exists, `started_at` < `ended_at`, both in the past. Creates in `Pending` status with `position_id`, `started_at`, `ended_at`. Fires `HoursSubmitted`.
- `approve(HourLog $hourLog, User $reviewer, array $tags = []): HourLog` — transitions `Pending → Approved`, sets `reviewed_by`. Propagates tags from Position + any reviewer-supplied tags onto HourLog. Fires `HoursApproved`.
- `reject(HourLog $hourLog, User $reviewer, ?string $notes = null): HourLog` — transitions `Pending → Rejected`, sets `reviewed_by`, optionally sets notes.

**Test (feature):**
- `signUp`: capacity enforcement rejects when full, duplicate prevention, past-shift rejection.
- `confirm` / `release`: correct transitions, `reviewed_by` set, events fired.
- `checkIn`: normal and self-check-in paths.
- `walkIn`: creates directly in CheckedIn, capacity checked.
- `checkOut`: `ended_at` set, tags propagated from Shift + Position, event fired.
- `submitHours`: validation (started_at < ended_at, both past), Pending status, event fired.
- `approve` / `reject`: transitions, tag propagation on approve, notes on reject.

---

## Epic 3: Permissions and policies

### 3.1 Seed volunteer permissions

Add to `database/seeders/PermissionSeeder.php` following the existing pattern:

- `volunteer.position.manage`
- `volunteer.shift.manage`
- `volunteer.manage`
- `volunteer.checkin`
- `volunteer.hours.approve`
- `volunteer.hours.submit`
- `volunteer.hours.report`
- `volunteer.signup`

Assign to appropriate roles: admin gets all, moderator gets a subset, member gets `volunteer.signup` and `volunteer.hours.submit`.

**Test:** Run seeder, verify permissions and role assignments exist.

### 3.2 Create authorization policies

Policies in `app/Policies/Volunteering/`:

**`PositionPolicy`** — gates create/update/delete on `volunteer.position.manage`.

**`ShiftPolicy`** — gates create/update/delete on `volunteer.shift.manage`.

**`HourLogPolicy`:**
- `signUp`: user has `volunteer.signup`.
- `confirm` / `release`: user has `volunteer.manage` OR user is the event's `organizer_id`.
- `checkIn` (others): user has `volunteer.checkin`.
- `checkIn` (self): allowed if the user's own HourLog is in `Confirmed` status. No permission needed.
- `checkOut` (self): allowed if the user's own HourLog is in `CheckedIn` status.
- `checkOut` (others): user has `volunteer.checkin`.
- `submitHours`: user has `volunteer.hours.submit`.
- `approve` / `reject`: user has `volunteer.hours.approve`.
- `viewReport`: user has `volunteer.hours.report`.

**Test (feature):**
- Coordinator can confirm/release for their own event but not others'.
- Supervisor (`volunteer.checkin`) can check in/out any volunteer.
- Volunteer can self-check-in only when Confirmed, self-check-out only when CheckedIn.
- Member can submit hours but not approve.
- Staff with `volunteer.hours.report` can view reports; members cannot.

---

## Epic 4: Filament Staff Panel — Roles and Shifts

### 4.1 Create PositionResource

`app/Filament/Staff/Resources/Volunteering/Positions/PositionResource.php` with the standard subdirectory structure (Pages/, Actions/).

Table columns: title, tags, shift count (aggregate), hour log count (aggregate), timestamps.

Form: title (required), description (rich text editor), tags (spatie tag input).

Relation managers: `ShiftsRelationManager` (upcoming shifts using this position), `HourLogsRelationManager` (recent hours logged under this position).

**Test (feature):** CRUD operations work, relation managers render.

### 4.2 Create ShiftResource

`app/Filament/Staff/Resources/Volunteering/Shifts/ShiftResource.php`

Table columns: position title, event name (if linked), start/end, capacity display ("2/3 filled"), tags, timestamps.

Filters: position select, event select, date range, tag select.

Form: position select (excludes soft-deleted), event select (optional), start_at, end_at, capacity, tags.

Relation manager: `HourLogsRelationManager` — volunteer name, status badge, started_at, ended_at, minutes. Row actions as extracted Filament action classes: `ConfirmVolunteerAction`, `ReleaseVolunteerAction`, `CheckInAction`, `CheckOutAction`. Each gated on appropriate policy method and valid state transition.

**Test (feature):** CRUD, filters, relation manager actions trigger correct service methods.

### 4.3 Add VolunteerShiftsRelationManager to EventResource

Integration layer: `app/Filament/Staff/Resources/Events/RelationManagers/VolunteerShiftsRelationManager.php`.

Shows all Shifts for this event with volunteer counts and status. Actions: Add Shift (modal with role select and capacity), Confirm/Release/CheckIn/CheckOut inline.

This is the interim day-of volunteer management UI.

**Test (feature):** Relation manager appears on EventResource, actions work.

### 4.4 Add VolunteerHourLogsRelationManager to UserResource

`app/Filament/Staff/Resources/Users/RelationManagers/VolunteerHourLogsRelationManager.php`

Shows a user's volunteer activity (all HourLogs). Columns: role (via shift or direct), event name (if shift-based), status badge, started_at, ended_at, minutes, tags. Footer: total approved/checked-out hours.

**Test (feature):** Relation manager renders on user view.

---

## Epic 5: Filament Staff Panel — Approval and Reporting

### 5.1 Create PendingHourLogsPage

Filament custom page at `app/Filament/Staff/Pages/PendingHourLogsPage.php`.

Shows all HourLogs in `Pending` status. Columns: volunteer name, role, submitted date, started_at, ended_at, minutes, notes.

Row actions: Approve (modal with optional tag input, calls `HourLogService::approve`), Reject (modal with notes field, calls `HourLogService::reject`).

Gated on `volunteer.hours.approve` permission.

**Test (feature):** Page renders pending logs. Approve action transitions to Approved with tags. Reject transitions to Rejected with notes. Non-pending logs don't appear.

### 5.2 Create VolunteerReportPage

Filament custom page at `app/Filament/Staff/Pages/VolunteerReportPage.php`.

Date range picker (defaults to current quarter) and optional tag filter.

Queries HourLogs in `CheckedOut` or `Approved` status within the date range.

Summary stats (Filament stat widgets): total volunteer hours, unique volunteer count, shifts staffed.

Tables: hours by volunteer (name, total hours, sessions) and hours by role (title, total hours, volunteer count). Sortable, exportable.

When tag filter is applied, all queries scope via `withAnyTags($tag)`.

Gated on `volunteer.hours.report` permission.

**Test (feature):** Correct totals (only CheckedOut + Approved count). Date range filtering works. Tag filtering scopes correctly. Logs in other statuses excluded.

---

## Epic 6: Member Panel

### 6.1 Volunteer sign-up page

`app/Filament/Member/Resources/Volunteering/` or a custom page — list upcoming events with open shifts.

For each event: event name, date, list of shifts with role name and available capacity. "Sign Up" button calls `HourLogService::signUp`. Shows current sign-up status if already signed up.

Member can also view their upcoming confirmed shifts and past volunteer history (all their HourLogs).

Gated on `volunteer.signup` permission.

**Test (feature):** Member sees open shifts, can sign up, sees confirmation. Full shift not signable. Already-signed-up shift shows status instead of button.

### 6.2 Self-reported hours submission

Page or form in Member panel for submitting self-reported hours.

Form: position select (excludes soft-deleted), start date/time, end date/time, optional notes. Calls `HourLogService::submitHours`.

Shows submission history with status (Pending/Approved/Rejected) and reviewer notes.

Gated on `volunteer.hours.submit` permission.

**Test (feature):** Member submits hours, sees pending status. After staff approval, status updates. Validation rejects future dates.

### 6.3 Self-check-in and check-out

Members with a `Confirmed` HourLog for a current or upcoming shift (within 30 minutes of start through shift end) see a "Check In" action. After checking in, they see "Check Out".

Can be a widget on the member dashboard or inline on the sign-up page.

No special permission — `Confirmed` status on their own HourLog is the gate.

**Test (feature):** Confirmed volunteer sees check-in button within time window. CheckedIn volunteer sees check-out button. Non-confirmed or out-of-window shows nothing.

---

## Epic 7: Notifications

### 7.1 Shift confirmed notification

`app-modules/volunteering/src/Notifications/ShiftConfirmedNotification.php`

Email via Postmark: shift role, event name, date/time. Dispatched by listener on `VolunteerConfirmed`.

### 7.2 Shift released notification

`app-modules/volunteering/src/Notifications/ShiftReleasedNotification.php`

Email via Postmark: shift role, event name. Dispatched by listener on `VolunteerReleased`.

### 7.3 Shift reminder notification and scheduled command

`app-modules/volunteering/src/Notifications/ShiftReminderNotification.php`

Sent 24 hours before a shift's `start_at` to all volunteers in `Confirmed` status.

`app-modules/volunteering/src/Console/SendShiftReminders.php` — runs daily via scheduler. Queries shifts where `start_at` is between now+23h and now+25h. Sends reminder to confirmed volunteers. Idempotent — tracks sent reminders via cache key or `reminded_at` timestamp.

### 7.4 Hours reviewed notification

`app-modules/volunteering/src/Notifications/HoursReviewedNotification.php`

Sent when self-reported hours are approved or rejected. Includes role, hours, and reviewer notes (if rejected). Dispatched by listeners on `HoursApproved` and `Pending → Rejected` transition.

### 7.5 Hours submitted (staff notification)

`app-modules/volunteering/src/Notifications/HoursSubmittedNotification.php`

Sent to users with `volunteer.hours.approve` permission when a volunteer submits hours. Dispatched by listener on `HoursSubmitted`.

**Test (all notifications):** Each notification is sent to the correct recipient with correct content. Shift reminder only fires for shifts in the 23-25h window. Staff notification reaches all approvers.

---

## Epic 8: Event cancellation integration

### 8.1 Release linked volunteers on event cancellation

Integration-layer listener at `app/Listeners/Volunteering/ReleaseVolunteersOnEventCancelled.php`.

Listens for the Events module's cancellation event. When an event is cancelled:
- Find all Shifts with `event_id` matching the cancelled event.
- Transition all non-terminal HourLogs for those shifts to `Released` via `HourLogService::release`.
- Affected volunteers receive the shift released notification.

**Test (integration):** Cancel an event → all linked HourLogs transition to Released → volunteers notified.

---

## Smoke tests

Two end-to-end scenarios that exercise the full feature lifecycle. Run these before considering the module shippable.

**1. Full shift lifecycle.** Staff creates a Position ("Sound Person"). Staff creates a Shift for an upcoming event (capacity 2). Member A signs up from Member panel → HourLog in Interested. Coordinator confirms Member A → HourLog in Confirmed, notification sent. 24 hours before shift, reminder notification sent. Day of: supervisor checks in Member A → CheckedIn, `started_at` set. Walk-in Member B added by supervisor → CheckedIn directly. Both check out → CheckedOut, `ended_at` set, tags propagated from Shift and Position. Report page shows both volunteers' hours with correct totals and tags.

**2. Full self-reported lifecycle.** Member submits 3 hours of "Grant Writer" work from Member panel → HourLog in Pending, staff notified. Staff approves with tag "Spring Grant 2026" → Approved, Role tags + reviewer tag propagated, volunteer notified. Report page filtered by "Spring Grant 2026" shows the hours.

---

## Dependency graph

```
Epic 1 (models, migrations, states, events)
  └─▶ Epic 2 (services)
        ├─▶ Epic 3 (permissions, policies)
        │     ├─▶ Epic 4 (staff panel: roles, shifts, event RM)
        │     │     └─▶ Epic 5 (staff panel: approval, reporting)
        │     └─▶ Epic 6 (member panel)
        ├─▶ Epic 7 (notifications)
        └─▶ Epic 8 (event cancellation)

Epics 4–8 are independent of each other (all depend on 2+3).
Epic 7 and 8 only depend on Epic 2 (no policy gating needed for listeners).
```

---

## Out of scope

From the design spec's Deferred section:

- **Credit earning** — Finance integration for volunteer hours → credits.
- **Public dynamic volunteer page** — replacing static `volunteer.blade.php`.
- **Event control panel** — consolidated day-of operations page (volunteers, tickets, till count, door fees).
- **Kiosk check-in view** — simplified desk terminal screen.
- **Shift templates / event type presets** — auto-creating shifts from templates.
- **Recurring shifts** — repeating shift patterns.
- **Waitlisting** — `Waitlisted` state when shifts are full.
- **Volunteer profiles / skills matching.**
- **Shift trading between volunteers.**
