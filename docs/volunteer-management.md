# Volunteer Management

Greenfield design for a Volunteer module. Nothing proposed here exists in the codebase today — the current volunteer page is static HTML with a stub interest form that emails `contact@corvmc.org`.

The central ideas: roles categorize volunteer work; shifts tie a role to a specific event; every record of volunteer work is an HourLog — whether from a shift check-in/check-out or a self-reported submission. The module is a ledger of work done, not an org chart — who holds what title is the domain of staff profiles.

---

## Why a module

CMC runs on volunteer labor — event support, facility care, social media, grant writing, administrative work. Today none of it is tracked in the application. Staff coordinate through email, spreadsheets, and memory. There's no way to answer "how many volunteer hours did we log this quarter?" for grant reporting, no way for a member to see what volunteer slots are open for Friday's show, and no way for the event coordinator to know who's signed up.

The module gives CMC three things it doesn't have:

1. **A catalog of volunteer roles** with descriptions of what each job entails, so members know what they're signing up for.
2. **Shift scheduling tied to events**, so "we need a sound person for Friday's show" is a first-class object with sign-up, confirmation, and check-in/check-out.
3. **Hour tracking for reporting.** Every piece of volunteer work — shift or self-reported — lands in one table. Tag-based filtering makes grant reporting straightforward.

---

## Two kinds of work

**Event shifts** are time-bound. An event needs specific roles filled — a show needs a door volunteer, a sound person, and a host; a cleanup day needs workers; a repair day needs techs. Members sign up, the event coordinator confirms who's in, and on the day a volunteer supervisor checks people in and out. Hours fall out of the timestamps.

**Self-reported work** has no event or shift. A volunteer does social media work, writes a grant, edits the newsletter — then submits hours categorized by role. Staff approves.

Both produce the same thing: an **HourLog** — one row per person per work session. The module tracks work done, not who holds what title. Role assignments and titles live on staff profiles.

---

## Domain model

### Role

A reusable definition of a volunteer job. "Sound Person", "Door Volunteer", "Host", "Tech", "Worker", "Social Media Coordinator", "Grant Writer." A Role describes what the job is, what skills are helpful, and what the responsibilities look like.

```php
namespace CorvMC\Volunteering\Models;

class Role extends Model
{
    // title: string — "Sound Person", "Host", etc.
    // description: text — what this role does, expectations, skills
}
```

Roles are created by staff and reused across events and self-reported work. The same "Host" role applies to shows, meetups, and cleanup days. A "Grant Writer" role categorizes self-reported hours. Which roles a specific event needs is decided per-event via Shifts.

**Tagging.** Roles are taggable via `spatie/laravel-tags`. Tagging a Role means "all hours worked under this role count toward this grant/project." Role tags propagate onto HourLogs when hours are finalized. This is the broadest tagging level — tag "Grant Writer" with "Spring Grant 2026" and every approved hour log for that role inherits the tag automatically.

### Shift

A specific need: "Event X needs Role Y filled, with Z slots." One Shift row per role per event.

```php
class Shift extends Model
{
    // role_id: FK to roles
    // event_id: nullable FK to events (nullable for non-event shifts like standalone facility work)
    // start_at: timestamp
    // end_at: timestamp
    // capacity: integer — how many volunteers are needed
}
```

Friday's show gets three Shifts: Door/1, Sound/1, Host/1. A Saturday cleanup day gets one Shift: Worker/4. Each Shift knows exactly which role it needs and how many people.

**Tagging.** Shifts are taggable via `spatie/laravel-tags`. Staff tags a shift with "Spring Grant 2026" or "OCCF Capital Campaign" so its volunteer hours roll up in grant reports. Tags are staff-only. Shift tags propagate onto HourLogs at check-out.

**Event linkage.** `event_id` is a nullable foreign key to the Events module's `events` table. The Volunteering module stores the ID; the integration layer resolves the relationship via `resolveRelationUsing`. This keeps the modules decoupled the same way Finance doesn't import SpaceManagement models.

### HourLog

The universal record of volunteer work. Every piece of volunteering — a shift worked, a self-reported block of grant writing — is one HourLog row. "This person did this work."

```php
class HourLog extends Model
{
    // user_id: FK
    // shift_id: nullable FK — populated for shift-based work
    // role_id: nullable FK — populated for self-reported work (null for shift work, where the role is on the Shift)
    // status: HourLogState
    // started_at: nullable timestamp — set at check-in (shifts) or by volunteer (self-reported)
    // ended_at: nullable timestamp — set at check-out (shifts) or by volunteer (self-reported)
    // minutes: nullable integer — generated column, computed from started_at/ended_at by the database
    // reviewed_by: nullable user_id — staff who confirmed/released/approved/rejected
    // notes: nullable text
}
```

Two creation paths, one model:

**Shift-based.** Member signs up for a shift → HourLog created in `Interested` status. Coordinator confirms or releases. Supervisor (or volunteer themselves) checks in (sets `started_at`) and checks out (sets `ended_at`; `minutes` is derived automatically by the database). The HourLog is the sign-up, the attendance record, and the hour log all in one.

**Self-reported.** Volunteer submits hours for a role → HourLog created in `Pending` status with `started_at` and `ended_at` set by the volunteer; `minutes` is derived by the database. Staff approves or rejects.

**Capacity enforcement.** For shifts, creating a new HourLog checks the count of non-Released HourLogs for that shift against its capacity. Coordinators can override when adding walk-ins.

**Tagging.** HourLogs are taggable via `spatie/laravel-tags`. Tags from the parent Shift and Role propagate onto the HourLog when hours are finalized. Staff can also apply additional tags directly. See "Tagging and propagation."

---

## State machines

### HourLogState

One state machine covering both shift-based and self-reported work.

**Shift lifecycle:**

```
Interested ──(coordinator confirms)──▶ Confirmed
    │                                      │
    ├──(coordinator releases)──▶ Released   ├──(supervisor/self checks in)──▶ CheckedIn
                                           │                                    │
                                           ├──(released)──▶ Released            ├──(supervisor/self checks out)──▶ CheckedOut
                                                                                │
                                                                                └──(released)──▶ Released
```

**Self-reported lifecycle:**

```
Pending ──(staff approves)──▶ Approved
    │
    └──(staff rejects)──▶ Rejected
```

States:

- **Interested** — member signed up for a shift. They want to help. Default for shift-based work.
- **Confirmed** — coordinator has reviewed and approved this person for the shift. "You're on the schedule."
- **Released** — coordinator decided this person isn't needed, or the volunteer withdrew. Terminal.
- **CheckedIn** — volunteer has arrived and started working. `started_at` is set.
- **CheckedOut** — volunteer has finished and left. `ended_at` is set; `minutes` is derived by the database. Terminal. Hours count toward reporting.
- **Pending** — volunteer submitted self-reported hours. Awaiting staff review. Default for self-reported work.
- **Approved** — staff confirmed self-reported hours. Terminal. Hours count toward reporting.
- **Rejected** — staff denied self-reported hours. Terminal.

**Released vs. Cancelled.** "Released" rather than "Cancelled" because the coordinator is making a judgment call ("we have enough people" or "not the right fit for this one"), not voiding something that was broken.

**Which hours count.** Only `CheckedOut` and `Approved` HourLogs contribute to reporting totals.

---

## Event volunteering flow

The end-to-end lifecycle for event-based volunteer work:

### 1. Setup (staff)

Staff creates Shifts for the event. "Friday's show needs 1 Sound Person, 1 Door Volunteer, 1 Host." Three Shift rows, each referencing a Role and the Event.

Shifts can be added to an event at any point before it starts.

### 2. Sign-up (members)

Members browse upcoming events that have open shifts and indicate interest. Where this happens is TBD — could be a member-facing Filament panel, a page in the authenticated app, or part of the deferred public volunteer page. System creates an HourLog in `Interested` status linked to the Shift. A member can sign up for multiple shifts across different events, or multiple roles at the same event (though that's unusual).

### 3. Pre-event curation (event coordinator)

The event's coordinator (the `organizer_id` on the Event) reviews who has signed up. They confirm the people they want and release the rest. This is a judgment call — could be first-come-first-served, could be based on experience, could be "we already have a sound person."

### 4. Day-of check-in (volunteer supervisor)

A volunteer supervisor (any member with the `volunteer.checkin` permission) opens the check-in view on their phone. The UI defaults to showing shifts for events happening now, so the supervisor sees the relevant confirmed volunteers without navigating. As each person arrives, the supervisor checks them in — tapping their name sets `started_at` and transitions to `CheckedIn`.

**Walk-ins.** If someone who didn't sign up shows up and wants to help, the supervisor can add them on the spot. This creates an HourLog in `CheckedIn` status directly, bypassing Interested → Confirmed. The supervisor picks which shift the walk-in is filling.

**Self-check-in.** A volunteer with a `Confirmed` HourLog for a shift can also check themselves in from their own phone (no special permission needed — the confirmed status is the gate). The supervisor can see this reflected in their view and can always override.

### 5. Check-out (volunteer or supervisor)

When the volunteer is done, they check themselves out (from their phone) or the supervisor checks them out. `ended_at` is set, `minutes` is derived automatically, HourLog transitions to `CheckedOut`. Tags from the Shift and Role propagate onto the HourLog.

If a volunteer forgets to check out, the supervisor can close them out later or staff can do it from Filament.

---

## Self-reported work flow

The lifecycle for non-event volunteer work:

### 1. Submission (volunteer)

Volunteer submits hours for a role: "I spent 3 hours on social media content Tuesday." System creates an HourLog in `Pending` status with `role_id` set, `started_at`/`ended_at`/`minutes` filled in by the volunteer.

### 2. Approval (staff)

Staff reviews pending submissions in a Filament queue. Approves or rejects, optionally with a note and tags. Tags from the Role propagate onto the HourLog at approval. Only approved hours count toward reporting.

---

## Module boundaries

Volunteering is a self-contained module at `app-modules/volunteering/`. It follows the same modular pattern as Events, SpaceManagement, and Finance.

**Volunteering module** (`app-modules/volunteering/`):
- Models: `Role`, `Shift`, `HourLog`
- States: `HourLogState` (spatie/laravel-model-states)
- Actions: `CreateRole`, `CreateShift`, `SignUp`, `ConfirmVolunteer`, `ReleaseVolunteer`, `CheckIn`, `CheckOut`, `SubmitHours`, `ApproveHours`, `RejectHours`
- Events: `VolunteerConfirmed`, `VolunteerReleased`, `VolunteerCheckedIn`, `VolunteerCheckedOut`, `HoursSubmitted`, `HoursApproved`
- No dependency on Events, SpaceManagement, or Finance modules

**Integration layer** (`app/`):
- Event relationship resolution: `Shift::event()` via `resolveRelationUsing` wired in `AppServiceProvider`, so the Volunteering module stores `event_id` but never imports `CorvMC\Events\Models\Event`.
- Cross-module listeners: `ReleaseVolunteersOnEventCancelled` — on Event cancellation, releases all linked HourLogs.
- Policy classes for authorization.

**No Finance integration.** Volunteering doesn't earn credits, doesn't create Orders, and doesn't interact with the payment system. If credit-earning is added later, Volunteering fires `VolunteerCheckedOut`, a listener in `app/` calls `Finance::allocate()`.

---

## Schema

### `volunteer_roles`

```
id
title                string           — "Sound Person", "Host", "Grant Writer", etc.
description          text, nullable   — what this role does, expectations, skills
timestamps
```

Tagging via `spatie/laravel-tags`. Role-level tags propagate to HourLogs at finalization.

### `volunteer_shifts`

```
id
role_id              integer, FK
event_id             integer, nullable, FK — optional link to events.id
start_at             timestamp
end_at               timestamp
capacity             integer          — how many volunteers needed
timestamps
```

Indexes: `(event_id)` for "shifts for this event" lookups; `(role_id)` for "all shifts for this role"; `(start_at)` for chronological browsing.

Tagging via `spatie/laravel-tags`.

### `volunteer_hour_logs`

```
id
user_id              integer, FK
shift_id             integer, nullable, FK — populated for shift-based work
role_id              integer, nullable, FK — populated for self-reported work
status               HourLogState      — Interested, Confirmed, Released, CheckedIn, CheckedOut, Pending, Approved, Rejected
started_at           timestamp, nullable
ended_at             timestamp, nullable
minutes              integer, generated — EXTRACT(EPOCH FROM (ended_at - started_at)) / 60; null when either timestamp is null
reviewed_by          integer, nullable, FK — staff who acted on this (confirm/release/approve/reject)
notes                text, nullable
timestamps
```

Check constraint: exactly one of `shift_id` or `role_id` must be non-null.

Unique constraint on `(user_id, shift_id)` where `status not in ('released', 'checked_out')` — prevents double-signup for the same shift.

Indexes: `(shift_id, status)` for "who's signed up for this shift" queries; `(user_id, status)` for "my volunteer activity"; `(status)` for the approval queue.

Tagging via `spatie/laravel-tags`. Tags from Shift and Role propagate at finalization (check-out or approval).

---

## Tagging and propagation

Roles, Shifts, and HourLogs are all taggable via `spatie/laravel-tags`. Staff applies tags at the Role or Shift level; tags propagate down to HourLogs automatically.

**Propagation rules:**
- **At check-out** (shift work): tags from the Shift and the Shift's Role are merged and copied onto the HourLog.
- **At approval** (self-reported work): tags from the Role are copied onto the HourLog. Staff can also add tags directly during approval.

**Why propagate rather than join?** Grant reporting needs to query "total hours tagged X." With tags denormalized onto HourLogs, this is a single `withAnyTags()` query. Without propagation, every report would need to join through Shift → Role and resolve tags at each level.

**Tags accumulate.** A Shift tagged "OCCF Capital Campaign" whose Role is tagged "Spring Grant 2026" produces HourLogs with both tags. The report page can filter by either.

---

## Reporting

All volunteer hours live in one table: `volunteer_hour_logs`. Only rows in `CheckedOut` or `Approved` status count.

The report page accepts a date range (defaults to current quarter) and an optional tag filter. "Total hours tagged Spring Grant 2026" is a single query: `HourLog::withAnyTags('Spring Grant 2026')->whereIn('status', ['checked_out', 'approved'])->sum('minutes')`.

Breakdowns by volunteer, by role, and by shift/event are straightforward group-by queries on the same table.

---

## Filament Admin

### RoleResource

**`RoleResource`** — manages the catalog of volunteer roles.
- Columns: title, tags, shift count, hour log count, timestamps.
- Form: title, description (rich text), tags (spatie tag input).
- Relation managers: `ShiftsRelationManager` (upcoming shifts using this role), `HourLogsRelationManager` (recent hours logged under this role).

### ShiftResource

**`ShiftResource`** — manages specific shift needs.
- Columns: role title, event name, start/end, capacity (filled/total), tags, timestamps.
- Filters: role, event, date range, tag.
- Form: role select, event select (optional), start_at, end_at, capacity, tags (spatie tag input).
- Relation manager: `HourLogsRelationManager` — who's signed up, their status. Actions: Confirm, Release, Check In, Check Out.

### Event volunteer management

Relation manager on EventResource (integration layer): **`VolunteerShiftsRelationManager`** — shows all Shifts for this event, with nested volunteer counts. Actions: Add Shift (select role, set capacity), quick view of who's signed up per shift.

### Hour approval queue

**`PendingHourLogsPage`** — Filament custom page showing all HourLogs in `Pending` status. Columns: volunteer name, role, submitted date, started_at, ended_at, minutes, notes. Row actions: Approve (with optional tag input), Reject (with notes modal). Gated on `volunteer.hours.approve` permission.

### Volunteer report page

**`VolunteerReportPage`** — date-range picker (defaults to current quarter) and optional tag filter. Queries HourLogs in `CheckedOut` or `Approved` status. Shows total hours by volunteer, total hours by role, and aggregate totals. Sortable, exportable. Gated on `volunteer.hours.report` permission.

### User relation managers

- `VolunteerHourLogsRelationManager` — shows a user's volunteer activity (all HourLogs, all statuses).

---

## Notifications

- **Shift confirmed** — notify volunteer when coordinator confirms them for a shift.
- **Shift reminder** — notify confirmed volunteers 24 hours before a shift starts. Scheduled daily job.
- **Hours approved/rejected** — notify volunteer when their self-reported hours are reviewed.
- **Hours submitted (staff)** — notify staff when a volunteer submits hours for approval, so the queue doesn't go stale.

All notifications use Laravel's notification system with the existing Postmark mail channel.

---

## Permissions

Using the existing spatie/laravel-permission system:

- `volunteer.role.manage` — create/edit roles (staff)
- `volunteer.shift.manage` — create/edit shifts (staff)
- `volunteer.manage` — confirm/release volunteers (staff, event coordinators)
- `volunteer.checkin` — check volunteers in/out at events (volunteer supervisors). Granted to trained members. The check-in UI defaults to current events so supervisors don't need to navigate.
- `volunteer.hours.approve` — approve/reject self-reported hours (staff)
- `volunteer.hours.submit` — submit self-reported hours (all members)
- `volunteer.hours.report` — view aggregate hour reports (staff, board)
- `volunteer.signup` — sign up for open shifts (all members, default)

Event coordinator authority to confirm/release volunteers for their own events is derived from being the event's `organizer_id`, not from `volunteer.manage` (which is the global staff override).

---

## What changes

| Area | Change |
|---|---|
| Volunteer coordination | Moves from email/spreadsheets into Filament admin and in-app sign-up/check-in |
| User model | Gains `volunteerHourLogs()` relationship (via `resolveRelationUsing` in integration layer) |
| Events module | Unchanged internally. Gains a `VolunteerShiftsRelationManager` in Filament (integration layer). `events.id` is referenced by FK from `volunteer_shifts.event_id` but Events doesn't know about Volunteering |
| EventResource (Filament) | Gains a volunteer shifts relation manager tab |
| `resources/views/public/volunteer.blade.php` | Unchanged. Static page stays as-is; dynamic replacement is deferred |

---

## What doesn't change

| Area | Notes |
|---|---|
| Finance module | No integration. Volunteering doesn't produce Orders, Transactions, or wallet movements |
| SpaceManagement module | No integration. Shifts don't interact with reservations |
| Events module internals | Events doesn't import Volunteering types. The FK is one-directional |
| Membership module | Unchanged. Volunteer eligibility is "must have a User account" — no new membership tier |
| Permission system | Uses existing spatie/laravel-permission. New permissions added but the system itself is unchanged |

---

## Deferred

- **Credit earning** — volunteers earning practice space credits for hours worked. Architecture supports it (Volunteering fires events, Finance listens), but no demand yet.
- **Public dynamic volunteer page** — replacing the static `volunteer.blade.php` with a database-driven listing of open shifts and authenticated sign-up. The static page stays for now.
- **Kiosk check-in view** — simplified check-in screen for the desk terminal. Check-in works via the web app on a phone for now.
- **Shift templates / event type presets** — auto-creating shifts from a template when an event is created (e.g., "every show needs Sound/Door/Host"). Likely merges into a broader EventType formalization that other modules would also use. For now, shifts are created manually per event.
- **Recurring shifts** — a repeating shift pattern that auto-creates instances. Could use the existing `HasRecurringSeries` concern.
- **Waitlisting** — when a shift is full, letting members join a waitlist. Would add a `Waitlisted` state.
- **Volunteer profiles/skills** — tracking skills and preferences for better matching.
- **Shift trading** — volunteers swapping shifts with each other.

---

## Open questions

None blocking. Design is specified; implementation plan can be written.
