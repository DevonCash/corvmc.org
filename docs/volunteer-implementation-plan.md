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
│   └── migrations/
├── src/
│   ├── Actions/
│   ├── Concerns/
│   ├── Events/
│   ├── Exceptions/
│   ├── Models/
│   ├── Providers/
│   │   └── VolunteeringServiceProvider.php
│   └── States/
└── tests/
```

Register the service provider. Verify the module loads and the test suite still passes.

### 1.2 Create Role model and migration

New table `volunteer_roles`:

```
id, title (string), description (text nullable), timestamps
```

Model at `app-modules/volunteering/src/Models/Role.php`. Add `HasTags` trait from `spatie/laravel-tags`.

### 1.3 Create Shift model and migration

New table `volunteer_shifts`:

```
id, role_id (FK), event_id (int nullable FK to events.id),
start_at (timestamp), end_at (timestamp), capacity (integer),
timestamps
```

Model at `app-modules/volunteering/src/Models/Shift.php`. Add `HasTags` trait. Scopes: `forEvent($eventId)`, `upcoming()`, `open()` (capacity not filled). Indexes on `(event_id)`, `(role_id)`, `(start_at)`.

### 1.4 Create HourLog model, migration, and state machine

New table `volunteer_hour_logs`:

```
id, user_id (FK), shift_id (int nullable FK), role_id (int nullable FK),
status (HourLogState), started_at (timestamp nullable),
ended_at (timestamp nullable),
minutes (integer GENERATED ALWAYS AS (EXTRACT(EPOCH FROM (ended_at - started_at)) / 60) STORED),
reviewed_by (int nullable FK), notes (text nullable),
timestamps
```

Model at `app-modules/volunteering/src/Models/HourLog.php`. Add `HasTags` trait.

Check constraint: exactly one of `shift_id` or `role_id` must be non-null.

Partial unique index on `(user_id, shift_id)` excluding `released` and `checked_out` statuses.

Indexes on `(shift_id, status)`, `(user_id, status)`, `(status)`.

State classes under `app-modules/volunteering/src/States/HourLogState/`:

**Shift lifecycle:** `Interested`, `Confirmed`, `Released`, `CheckedIn`, `CheckedOut`

**Self-reported lifecycle:** `Pending`, `Approved`, `Rejected`

Allowed transitions:
- `Interested → Confirmed`, `Interested → Released`
- `Confirmed → CheckedIn`, `Confirmed → Released`
- `CheckedIn → CheckedOut`, `CheckedIn → Released`
- `Pending → Approved`, `Pending → Rejected`

Terminal states: `Released`, `CheckedOut`, `Approved`, `Rejected`.

### 1.5 Create domain events

- `VolunteerConfirmed` — carries HourLog. Fired on `Interested → Confirmed`.
- `VolunteerReleased` — carries HourLog. Fired on transition to `Released`.
- `VolunteerCheckedIn` — carries HourLog. Fired on `Confirmed → CheckedIn`.
- `VolunteerCheckedOut` — carries HourLog. Fired on `CheckedIn → CheckedOut`.
- `HoursSubmitted` — carries HourLog. Fired on self-reported HourLog creation.
- `HoursApproved` — carries HourLog. Fired on `Pending → Approved`.

All under `app-modules/volunteering/src/Events/`.

### 1.6 Wire relationships in integration layer

In `AppServiceProvider::boot()`:

- `Shift::resolveRelationUsing('event', ...)` — `belongsTo(Event::class, 'event_id')`.
- `User::resolveRelationUsing('volunteerHourLogs', ...)` — `hasMany(HourLog::class)`.

---

## Epic 2: Core actions

The write operations. Each action uses `lorisleiva/laravel-actions` following existing patterns.

### 2.1 CreateRole action

`app-modules/volunteering/src/Actions/CreateRole.php`

Creates a Role. Validates title is required, description optional.

### 2.2 CreateShift action

`app-modules/volunteering/src/Actions/CreateShift.php`

Creates a Shift. Validates: `role_id` exists, `start_at` < `end_at`, `capacity` >= 1, `event_id` optional and exists if provided.

### 2.3 SignUp action

`app-modules/volunteering/src/Actions/SignUp.php`

Member signs up for a shift:
- Checks shift exists and `start_at` is in the future.
- Checks capacity (non-Released, non-CheckedOut HourLog count < capacity).
- Checks user doesn't already have an active HourLog for this shift.
- Creates HourLog in `Interested` status with `shift_id` set.

### 2.4 ConfirmVolunteer action

`app-modules/volunteering/src/Actions/ConfirmVolunteer.php`

Transitions HourLog `Interested → Confirmed`. Sets `reviewed_by`. Fires `VolunteerConfirmed`.

### 2.5 ReleaseVolunteer action

`app-modules/volunteering/src/Actions/ReleaseVolunteer.php`

Transitions HourLog to `Released` from `Interested`, `Confirmed`, or `CheckedIn`. Sets `reviewed_by`. Fires `VolunteerReleased`.

### 2.6 CheckIn action

`app-modules/volunteering/src/Actions/CheckIn.php`

Transitions HourLog `Confirmed → CheckedIn`. Sets `started_at` to now. Fires `VolunteerCheckedIn`.

Also supports walk-ins: creates a new HourLog in `CheckedIn` status directly for a user + shift, setting `started_at` to now. Skips the Interested → Confirmed steps. Still checks capacity.

Also supports self-check-in: a volunteer with a `Confirmed` HourLog can check themselves in (no `volunteer.checkin` permission needed — the confirmed status is the gate).

### 2.7 CheckOut action

`app-modules/volunteering/src/Actions/CheckOut.php`

Transitions HourLog `CheckedIn → CheckedOut`. Sets `ended_at` to now. `minutes` is derived by the database. Fires `VolunteerCheckedOut`.

**Tag propagation.** After check-out, copies tags from the Shift and the Shift's Role onto the HourLog. Uses `syncTagsWithType` or `attachTags` to merge without overwriting any directly-applied tags.

### 2.8 SubmitHours action

`app-modules/volunteering/src/Actions/SubmitHours.php`

Volunteer submits self-reported hours:
- Validates `role_id` exists, `started_at` < `ended_at`, both in the past.
- Creates HourLog in `Pending` status with `role_id`, `started_at`, `ended_at` set.
- Fires `HoursSubmitted`.

### 2.9 ApproveHours action

`app-modules/volunteering/src/Actions/ApproveHours.php`

Transitions HourLog `Pending → Approved`. Sets `reviewed_by`. Optionally applies tags. Propagates tags from the Role onto the HourLog. Fires `HoursApproved`.

### 2.10 RejectHours action

`app-modules/volunteering/src/Actions/RejectHours.php`

Transitions HourLog `Pending → Rejected`. Sets `reviewed_by`. Accepts optional notes (reason for rejection).

---

## Epic 3: Permissions and policies

### 3.1 Create and seed volunteer permissions

Add permissions to the seeder (following existing pattern in `database/seeders/`):

- `volunteer.role.manage` — staff
- `volunteer.shift.manage` — staff
- `volunteer.manage` — staff, event coordinators
- `volunteer.checkin` — volunteer supervisors (trained members)
- `volunteer.hours.approve` — staff
- `volunteer.hours.submit` — all members
- `volunteer.hours.report` — staff, board
- `volunteer.signup` — all members (default)

### 3.2 Create authorization policies

Policies in `app/Policies/Volunteering/`:

- `RolePolicy` — gates create/edit on `volunteer.role.manage`.
- `ShiftPolicy` — gates create/edit on `volunteer.shift.manage`.
- `HourLogPolicy`:
  - Sign-up: `volunteer.signup` (member can only sign up themselves).
  - Confirm/release: `volunteer.manage` OR user is the event's `organizer_id`.
  - Check in/out (others): `volunteer.checkin`.
  - Self-check-in: allowed if the user's own HourLog is in `Confirmed` status.
  - Self-check-out: allowed if the user's own HourLog is in `CheckedIn` status.
  - Submit self-reported: `volunteer.hours.submit`.
  - Approve/reject: `volunteer.hours.approve`.

---

## Epic 4: Filament Admin — Roles and Shifts

### 4.1 Create RoleResource

Filament resource at `app/Filament/Resources/Volunteering/RoleResource.php`.

Table columns: title, tags, shift count (aggregate), hour log count (aggregate), timestamps.

Form: title (required), description (rich text editor), tags (spatie tag input).

Relation managers: `ShiftsRelationManager` (upcoming shifts using this role), `HourLogsRelationManager` (recent hours logged under this role).

### 4.2 Create ShiftResource

Filament resource at `app/Filament/Resources/Volunteering/ShiftResource.php`.

Table columns: role title, event name (if linked), start/end, capacity display ("2/3"), tags, timestamps.

Filters: role select, event select, date range, tag select.

Form: role select, event select (optional), start_at, end_at, capacity, tags (spatie tag input).

Relation manager: `HourLogsRelationManager` — shows who's signed up and their status. Columns: volunteer name, status badge, started_at, ended_at, minutes. Row actions: Confirm, Release, Check In, Check Out (each gated on appropriate permission and valid state transition).

### 4.3 Add VolunteerShiftsRelationManager to EventResource

Integration layer: `app/Filament/Resources/EventResource/RelationManagers/VolunteerShiftsRelationManager.php`.

Shows all Shifts for this event with nested volunteer counts. Actions: Add Shift (modal with role select and capacity), inline view of who's signed up per shift.

### 4.4 Add VolunteerHourLogsRelationManager to UserResource

Shows a user's volunteer activity (all HourLogs, all statuses). Columns: role (via shift or direct), event name (if shift-based), status badge, started_at, ended_at, minutes, tags. Footer: total approved/checked-out hours.

---

## Epic 5: Filament Admin — Approval and Reporting

### 5.1 Create PendingHourLogsPage

Filament custom page at `app/Filament/Pages/PendingHourLogsPage.php`.

Shows all HourLogs in `Pending` status. Columns: volunteer name, role, submitted date, started_at, ended_at, minutes, notes.

Row actions: Approve (opens modal with optional tag input), Reject (opens modal with notes field).

Gated on `volunteer.hours.approve` permission.

### 5.2 Create VolunteerReportPage

Filament custom page at `app/Filament/Pages/VolunteerReportPage.php`.

Date range picker (defaults to current quarter) and optional tag filter.

Queries `HourLog::whereIn('status', ['checked_out', 'approved'])`.

Summary stats (Filament stat widgets):
- Total volunteer hours in period
- Number of unique volunteers in period
- Number of shifts staffed in period

Tables:
- Hours by volunteer: name, total hours, number of sessions. Sortable, exportable.
- Hours by role: role title, total hours, number of volunteers. Sortable, exportable.

When tag filter is applied, all queries scope to `withAnyTags($tag)`.

Gated on `volunteer.hours.report` permission.

---

## Epic 6: Check-in UI

### 6.1 Create volunteer check-in page

A page in the authenticated app (not Filament admin) for volunteer supervisors to use on their phone at events.

Default view: shifts for events happening now (where `events.start_datetime <= now <= events.end_datetime`), grouped by event.

For each shift: role name, capacity, list of confirmed/checked-in volunteers with tap-to-check-in / tap-to-check-out buttons.

Walk-in button: opens modal to select a user and a shift, creates HourLog in `CheckedIn` directly.

Gated on `volunteer.checkin` permission.

### 6.2 Create volunteer self-check-in/out

Volunteers with a `Confirmed` HourLog see a "Check In" button for their upcoming shifts (within a reasonable window — e.g., 30 minutes before shift start through shift end). After checking in, they see a "Check Out" button.

Lightweight — can be a simple Livewire component on the member dashboard or event detail page.

---

## Epic 7: Notifications

### 7.1 Shift confirmed notification

`app-modules/volunteering/src/Notifications/ShiftConfirmedNotification.php`

Sent when a volunteer is confirmed for a shift. Email via Postmark: shift role, event name, date/time.

Dispatched by a listener on `VolunteerConfirmed`.

### 7.2 Shift reminder notification and scheduled command

`app-modules/volunteering/src/Notifications/ShiftReminderNotification.php`

Sent 24 hours before a shift's `start_at` to all volunteers in `Confirmed` status.

`app-modules/volunteering/src/Console/SendShiftReminders.php` — runs daily. Queries shifts where `start_at` is between now+23h and now+25h. Sends reminder to confirmed volunteers. Idempotent — tracks sent reminders via a cache key or `reminded_at` timestamp.

### 7.3 Hours approved/rejected notification

`app-modules/volunteering/src/Notifications/HoursReviewedNotification.php`

Sent when self-reported hours are approved or rejected. Includes role, hours, and reviewer notes (if rejected).

Dispatched by listeners on `HoursApproved` and `Pending → Rejected` transition.

### 7.4 Hours submitted (staff notification)

`app-modules/volunteering/src/Notifications/HoursSubmittedNotification.php`

Sent to staff with `volunteer.hours.approve` permission when a volunteer submits hours. Keeps the approval queue from going stale.

Dispatched by a listener on `HoursSubmitted`.

---

## Epic 8: Event cancellation integration

### 8.1 Release linked volunteers when Event is cancelled

Integration-layer listener at `app/Listeners/ReleaseVolunteersOnEventCancelled.php`.

Listens for the Event module's cancellation event. When an Event is cancelled:
- Find all Shifts with `event_id` matching the cancelled Event.
- Transition all non-terminal HourLogs for those shifts to `Released`.
- Notify affected volunteers.

---

## Epic 9: Tag propagation

### 9.1 Implement tag propagation on check-out

In the `CheckOut` action (or as a listener on `VolunteerCheckedOut`):
- Load the HourLog's Shift and the Shift's Role.
- Collect tags from both.
- Attach them to the HourLog via `attachTags()` (additive, preserves any directly-applied tags).

### 9.2 Implement tag propagation on approval

In the `ApproveHours` action (or as a listener on `HoursApproved`):
- Load the HourLog's Role.
- Collect its tags.
- Merge with any tags the reviewer applied directly.
- Attach to the HourLog.

### 9.3 Test tag propagation

- Tag a Role → approve self-reported hours → verify HourLog has Role's tags.
- Tag a Shift → check out volunteer → verify HourLog has Shift's tags.
- Tag both Role and Shift → check out → verify HourLog has both sets of tags.
- Add direct tag during approval → verify it coexists with propagated tags.

---

## Epic 10: Tests

Written alongside each epic but grouped here for visibility.

### 10.1 Unit tests for models and state machine

- Role: creation, tagging.
- Shift: creation, scopes (`upcoming`, `open`, `forEvent`), capacity calculation, tagging.
- HourLog: all state transitions (valid and invalid), generated `minutes` column, check constraint (exactly one of shift_id/role_id), unique constraint on (user_id, shift_id).

### 10.2 Feature tests for actions

- SignUp: capacity enforcement, duplicate prevention, past-shift rejection.
- ConfirmVolunteer / ReleaseVolunteer: state transitions, `reviewed_by` set, events fired.
- CheckIn: normal check-in, walk-in creation, self-check-in (confirmed gate).
- CheckOut: `ended_at` set, `minutes` derived, tags propagated, event fired.
- SubmitHours: validation (started_at < ended_at, both past), `Pending` status, event fired.
- ApproveHours / RejectHours: state transitions, tag propagation on approve, notes on reject.

### 10.3 Feature tests for policies

- Coordinator can confirm/release for their own event but not others.
- Supervisor can check in/out at any event.
- Volunteer can self-check-in only when `Confirmed`.
- Self-check-out only when `CheckedIn`.
- Member can submit hours but not approve.

### 10.4 Feature tests for Filament

- RoleResource and ShiftResource: CRUD, relation managers.
- PendingHourLogsPage: displays pending, approve/reject actions work.
- VolunteerReportPage: date range filtering, tag filtering, correct totals (only CheckedOut + Approved).
- VolunteerShiftsRelationManager on EventResource.

### 10.5 Integration tests

- Event cancellation cascade: Event cancelled → HourLogs released → volunteers notified.
- Full shift lifecycle: create shift → sign up → confirm → check in → check out → verify minutes, tags, reporting total.
- Full self-report lifecycle: submit hours → approve with tags → verify tags propagated, reporting total.
- Walk-in: supervisor adds walk-in → check out → hours counted.

---

## Dependency graph (epics)

```
Epic 1 (models, migrations, state machine)
  └─▶ Epic 2 (core actions)
        ├─▶ Epic 3 (permissions, policies)
        │     └─▶ Epic 4 (filament: roles, shifts)
        │           └─▶ Epic 5 (filament: approval, reporting)
        ├─▶ Epic 6 (check-in UI)
        ├─▶ Epic 7 (notifications)
        └─▶ Epic 9 (tag propagation)

Epic 8 (event cancellation) ── requires Epics 1+2, independent of 3-7
Epic 10 (tests) ── written alongside each epic, grouped for visibility
```

Epics 6, 7, 8, and 9 are independent of each other and can be done in any order after Epic 2. Epic 5 depends on Epic 4 which depends on Epic 3. Tests (Epic 10) should be written alongside each epic.

---

## Out of scope for this plan

These are listed in the design doc's Deferred section:

- **Credit earning for volunteer hours** — Finance integration via events.
- **Public dynamic volunteer page** — database-driven shift listing with sign-up.
- **Kiosk check-in view** — simplified desk terminal UI.
- **Shift templates / event type presets** — auto-creating shifts from templates.
- **Recurring shifts** — repeating shift patterns.
- **Waitlisting** — `Waitlisted` state when shifts are full.
- **Volunteer profiles / skills matching.**
- **Shift trading between volunteers.**
