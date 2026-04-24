# Invitations

Polymorphic invitation system for the support module. Replaces the `BandMember.status='invited'` pattern and adds event RSVP and band rehearsal attendance confirmation. The existing `app/Models/Invitation` for platform sign-ups is unchanged — it solves a different problem (identity creation with tokens and expiry) and stays purpose-built.

The central idea: an invitation is a request for someone to respond to something. "Join this band," "come to this event," and "can you make Thursday's rehearsal" are all the same shape — someone (or the system) asks a user to say yes or no. The model, the status lifecycle, and the notification mechanics are shared; only the side effects of acceptance differ.

---

## Why a shared pattern

Three things in the codebase use (or need) this shape today:

1. **Band membership** uses the `BandMember` pivot with `status='invited'`. The invitation lifecycle (pending, accepted, declined) is encoded in a membership table, mixing "wants to join" with "is a member." `SendBandMemberInvitationAction` creates the pivot entry directly; there's a TODO comment noting the email notification isn't wired up.
2. **Event attendance** has no mechanism. Free events and pay-at-door events have no way to gauge turnout. Staff plans blindly.
3. **Band rehearsal coordination** has no mechanism. Band admins coordinate attendance through group texts.

Each new feature that needs "ask someone to respond" would invent its own mechanism. The invitation model belongs in the support module so any module can depend on it.

---

## Domain model

### Invitation

One record per inviter/invitee/invitable combination. "Someone asked this person to respond to this thing."

```php
namespace CorvMC\Support\Models;

class Invitation extends Model
{
    protected $table = 'support_invitations';

    // inviter_id: nullable FK — who sent it. Null for self-initiated (event RSVPs).
    // user_id: FK — the invitee. Always an existing CMC member.
    // invitable_type: string — morph map alias ('event', 'band', 'rehearsal_reservation')
    // invitable_id: integer, FK — the thing being responded to
    // status: string — 'pending', 'accepted', 'declined'
    // data: JSON, nullable — type-specific context (role, position, comment, etc.)
    // responded_at: timestamp, nullable — when status changed from pending
    // timestamps
}
```

**Standard FK identity.** Every invitee is an existing CMC member — platform sign-ups are a separate system. `user_id` is a standard foreign key with referential integrity and a normal `belongsTo` relationship.

**Status semantics are universal.** `accepted` means "yes" — whether that's "I'm going to this event" or "I'll join this band." `declined` means "no." The UI labels these contextually ("Going" / "Not going" for events, "Accept" / "Decline" for band invitations), but the model stores one vocabulary.

**Data carries everything type-specific.** Role and position for band invitations, comment text for rehearsal attendance, any context the invitable type needs. No dedicated columns for things only some invitation types use.

---

## InvitationSubject contract and trait

Since the Invitation model lives in the support module — a dependency of every other module — subject models implement the contract directly. Same pattern as `HasTimePeriod` and `Recurrable`.

```php
namespace CorvMC\Support\Contracts;

interface InvitationSubject
{
    // Whether this instance currently accepts invitations.
    public function acceptsInvitations(): bool;

    // Users eligible to be invited. Null = any authenticated member.
    public function eligibleUsers(): ?Collection;

    // Whether users can invite themselves (event RSVPs) vs. requiring an inviter.
    public function allowsSelfInvite(): bool;

    // Side effects when an invitation is accepted or declined.
    public function onInvitationAccepted(Invitation $invitation): void;
    public function onInvitationDeclined(Invitation $invitation): void;
}
```

```php
namespace CorvMC\Support\Concerns;

trait HasInvitations
{
    public function invitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invitable');
    }

    public function pendingInvitations(): MorphMany
    {
        return $this->invitations()->where('status', 'pending');
    }

    public function acceptedInvitations(): MorphMany
    {
        return $this->invitations()->where('status', 'accepted');
    }
}
```

Each invitable model implements the interface and uses the trait:

```php
// In Event model (app-modules/events/)
class Event extends Model implements InvitationSubject
{
    use HasInvitations;

    public function acceptsInvitations(): bool
    {
        return $this->published_at !== null
            && $this->start_datetime->isFuture()
            && $this->status === EventStatus::Scheduled;
    }

    public function eligibleUsers(): ?Collection
    {
        return null; // Any authenticated member.
    }

    public function allowsSelfInvite(): bool
    {
        return true;
    }

    public function onInvitationAccepted(Invitation $invitation): void {}
    public function onInvitationDeclined(Invitation $invitation): void {}
}
```

```php
// In Band model (app-modules/bands/)
class Band extends Model implements InvitationSubject
{
    use HasInvitations;

    public function acceptsInvitations(): bool
    {
        return $this->status === 'active';
    }

    public function eligibleUsers(): ?Collection
    {
        $memberIds = $this->members()->pluck('users.id');
        return User::whereNotIn('id', $memberIds)->get();
    }

    public function allowsSelfInvite(): bool
    {
        return false;
    }

    public function onInvitationAccepted(Invitation $invitation): void
    {
        $role = $invitation->data['role'] ?? 'member';
        $position = $invitation->data['position'] ?? null;

        $this->members()->attach($invitation->user_id, [
            'role' => $role,
            'position' => $position,
        ]);
    }

    public function onInvitationDeclined(Invitation $invitation): void {}
}
```

```php
// In RehearsalReservation model (app-modules/space-management/)
class RehearsalReservation extends Reservation implements InvitationSubject
{
    use HasInvitations;

    public function acceptsInvitations(): bool
    {
        return $this->reservable_type === 'band'
            && $this->reserved_at->isFuture();
    }

    public function eligibleUsers(): ?Collection
    {
        return $this->reservable->activeMembers->pluck('user');
    }

    public function allowsSelfInvite(): bool
    {
        return false;
    }

    public function onInvitationAccepted(Invitation $invitation): void {}
    public function onInvitationDeclined(Invitation $invitation): void {}
}
```

---

## Status lifecycle

Three statuses: `pending`, `accepted`, `declined`.

**Allowed transitions:**

```
pending → accepted
pending → declined
accepted → declined    (change of mind — "actually I can't make it")
declined → accepted    (change of mind — "wait, I can come after all")
```

All four transitions are valid. For event RSVPs, toggling between accepted and declined is normal. For band invitations, changing your mind after accepting is unusual but possible — `onInvitationDeclined()` can clean up the pivot entry.

---

## Event RSVP flow

### 1. Member RSVPs to an event

Member visits a published event page. Below the event details (and below any ticket purchase UI), an RSVP section shows the member's current status as a toggle: "Going" / "Not going" / "RSVP" (if no invitation exists yet).

Clicking "Going" creates an Invitation with `invitable_type='event'`, `user_id` set, `inviter_id=null` (self-initiated), `status=accepted`, `responded_at` set immediately. Toggling calls the status transition. A comment can be stored in `data.comment`.

No notification is sent for self-initiated event invitations. The signal is passive — staff checks the count when they need it.

### 2. Staff views invitation data

In Filament, the EventResource gains an invitation section:

- **Infolist panel**: count of accepted / declined.
- **Relation manager** (`InvitationsRelationManager`): table of invitations with member name, status badge, comment from data, responded_at. Sortable, filterable, exportable.

Invitation data is staff-only in Filament. Members see their own status and the aggregate count on the event page, not other members' responses.

### 3. Which events accept invitations

All published future events. No per-event toggle. If an event has native ticketing, invitations and tickets coexist — a member might RSVP before buying a ticket, or RSVP if they plan to pay at the door.

Cancelled and past events don't accept new invitations (enforced by `Event::acceptsInvitations()`).

---

## Band membership invitation flow

Replaces the current `BandMember.status='invited'` pattern. The `BandMember` pivot table becomes a record of active membership only — no more 'invited' status.

### 1. Band admin invites a member

Band admin clicks "Invite member" in the band panel. Searches for an existing CMC member. System creates an Invitation with `invitable_type='band'`, `invitable_id=band.id`, `user_id` set, `inviter_id` set to the admin, `status=pending`, and `data` containing `role` and optional `position`.

### 2. Member receives notification

The invitee receives a notification with band name, inviter, assigned role/position, and Accept / Decline action links. Same content as today's `BandInvitationNotification`, now backed by the Invitation model.

### 3. Member responds

Accepting transitions `pending → accepted` and triggers `Band::onInvitationAccepted()`, which attaches the user to the `members()` relationship with role and position from `data`. The 'invited' status no longer exists on the pivot — BandMember only holds active members.

Declining transitions `pending → declined`. No pivot entry is created. The Invitation record is the audit trail.

### 4. What changes from today

- `BandMember.status` drops the `'invited'` value. Column can be removed or retained for future statuses (e.g. `'suspended'`).
- `BandMember.invited_at` column is dropped — the Invitation's `created_at` serves the same purpose.
- `SendBandMemberInvitationAction` creates an Invitation instead of a BandMember pivot entry.
- `AcceptBandInvitationPage` reads from Invitation instead of querying BandMember where status='invited'.
- `PendingBandInvitationsWidget` queries Invitation where `invitable_type='band'` and `status='pending'`.

---

## Band rehearsal attendance flow

### 1. Band admin requests attendance

After creating a band reservation in the Band panel, the reservation detail view includes a "Request attendance confirmation" action. Clicking it creates an Invitation for each active band member (excluding the admin — they're implicitly going) with `invitable_type='rehearsal_reservation'`, `status=pending`, and `inviter_id` set to the admin.

The existence of Invitation records is the opt-in — no column on the reservation. The action is idempotent: re-triggering creates pending invitations for any member who doesn't have one yet without disturbing existing responses.

### 2. Members receive notifications

Each member gets a notification: "{Band name} has a rehearsal on {date} at {time}. Can you make it?" with Going / Not Going action links.

### 3. Band admin views the roster

The reservation detail in the Band panel shows an attendance roster: each band member's name, status (accepted / declined / pending), comment from `data`. Summary line: "3 going, 1 not going, 1 pending."

The admin can manually change a member's status if a member responded out-of-band.

---

## Module boundaries

The Invitation model and its contracts live in the support module. Subject models implement `InvitationSubject` and use `HasInvitations` directly — no registration step.

**Support module** (`app-modules/support/`):
- Model: `Invitation`
- Contract: `InvitationSubject`
- Trait: `HasInvitations`
- Events: `InvitationCreated`, `InvitationAccepted`, `InvitationDeclined`
- Service: `InvitationService` — core operations (create, respond, prompt group, retract)

**Events module** — `Event` implements `InvitationSubject`, uses `HasInvitations`. No other changes.

**Bands module** — `Band` implements `InvitationSubject`, uses `HasInvitations`. `Band::onInvitationAccepted()` attaches the member to the pivot. `BandMember` drops `status` and `invited_at` columns.

**SpaceManagement module** — `RehearsalReservation` implements `InvitationSubject`, uses `HasInvitations`. No schema changes.

**Integration layer** (`app/`):
- Policy class: `InvitationPolicy`.
- Filament relation managers and UI components.
- Notification classes.
- Migration of `BandMember` invited status to Invitation records.

**Unchanged**: `app/Models/Invitation` (platform sign-ups), Finance module, Volunteering module, existing permission system.

---

## Schema

### `support_invitations`

```
id
inviter_id           integer, nullable, FK  — who sent it
user_id              integer, FK            — the invitee
invitable_type       string                 — morph map alias ('event', 'band', 'rehearsal_reservation')
invitable_id         integer, FK            — the thing being responded to
status               string                 — 'pending', 'accepted', 'declined'
data                 JSON, nullable         — type-specific context (role, position, comment, etc.)
responded_at         timestamp, nullable
timestamps
```

Unique constraint on `(user_id, invitable_type, invitable_id)` — one invitation per user per thing.

Indexes: `(invitable_type, invitable_id, status)` for "who's been invited to this thing" queries; `(user_id, status)` for "what has this user been invited to."

Separate from the existing `invitations` table used by platform sign-ups.

### `band_profile_members` (existing table, changes)

```
-- Remove:
status               — drop column (was 'active'|'invited', now always active)
invited_at           — drop column (replaced by invitation.created_at)

-- The check constraint on status is removed with the column.
```

### Migration of existing data

Each `BandMember` row with `status='invited'` becomes an Invitation with `invitable_type='band'`, `invitable_id=band_profile_id`, `user_id` from the pivot, `status='pending'`, `data={'role': role, 'position': position}`, `created_at=invited_at`. Remaining active rows are untouched. The `status` and `invited_at` columns are dropped after backfill.

---

## Notifications

- **Band membership invitation** — replaces today's `BandInvitationNotification`. Same content (band name, inviter, role, accept/decline links), now backed by the Invitation model. Mail + database channels.
- **Rehearsal attendance requested** — sent when a band admin prompts attendance for a reservation. Band name, date/time, going/not going action links. Mail + database channels.
- **Rehearsal reminder** — sent 24 hours before a reservation to members with `status=pending` who haven't responded. Single reminder, not recurring. Scheduled daily job.

No notifications for self-initiated event RSVPs — those are passive.

---

## Permissions

No new spatie/laravel-permission entries. Authorization via `InvitationPolicy`:

- **Self-inviting to an event**: any authenticated member, gated by `acceptsInvitations()`.
- **Inviting someone to a band**: band admin or owner.
- **Prompting rehearsal attendance**: band admin or owner.
- **Viewing invitation data**: staff (Filament), band admin/owner (band panel for their band's invitations), the invitee (their own invitations).
- **Responding**: the invitee only (or band admin manually updating a member's rehearsal status).

---

## Filament admin

### EventResource

**Infolist section**: "RSVPs" panel showing accepted count, declined count. Visible to staff.

**Relation manager**: `InvitationsRelationManager` — table of invitations with member name, status badge, comment from data, responded_at. Filterable by status.

### Band panel

**BandMembersResource**: "Invite member" action creates an Invitation instead of a BandMember pivot entry. Pending invitations section shows Invitations where `invitable_type='band'` and `status='pending'`.

**BandReservationsResource**: reservation detail gains "Request attendance confirmation" action and attendance roster.

### UserResource

`InvitationsRelationManager` — shows a user's invitation history across all invitable types. Read-only for staff context.

---

## What changes

| Area | Change |
|---|---|
| Support module | Gains Invitation model, InvitationSubject contract, HasInvitations trait, InvitationService |
| Event model | Implements `InvitationSubject`, uses `HasInvitations` |
| Band model | Implements `InvitationSubject`, uses `HasInvitations`. `onInvitationAccepted()` creates pivot entry |
| RehearsalReservation model | Implements `InvitationSubject`, uses `HasInvitations` |
| `BandMember` pivot | Drops `status` and `invited_at` columns. Only holds active members |
| `SendBandMemberInvitationAction` | Creates an Invitation instead of a BandMember pivot entry |
| `AcceptBandInvitationPage` | Reads from Invitation model |
| `PendingBandInvitationsWidget` | Queries Invitation where `invitable_type='band'`, `status='pending'` |
| Event public page | Gains RSVP section for authenticated members |
| EventResource (Filament) | Gains invitations infolist panel and relation manager |
| BandReservationsResource (Band panel) | Gains attendance confirmation action and roster |
| Morph map | Gains `'invitation' => Invitation::class` entry |

---

## What doesn't change

| Area | Notes |
|---|---|
| `app/Models/Invitation` | Unchanged. Platform sign-ups stay on their own model with tokens, expiry, and account creation |
| SpaceManagement module | No schema changes, no new columns on reservations |
| Finance module | No interaction. Invitations don't create Orders, Transactions, or wallet movements |
| Ticket system | Invitations coexist with tickets. A ticket purchase doesn't auto-create an invitation or vice versa |
| Volunteering module | No interaction now. Future integration possible (shift sign-ups as invitations) |
| `BandService::addMember()` | Unchanged — still available for direct member addition without an invitation flow |

---

## Deferred

- **Public RSVP counts on events** — showing "23 going" on the public event page as social proof. Currently staff-only. Could be a per-event toggle.
- **Guest RSVPs** — allowing non-members to RSVP to public events via email.
- **Volunteer shift invitations** — Volunteering's HourLog tracks sign-up/confirmation, but invitations could supplement or replace the Interested → Confirmed flow.
- **Calendar integration** — adding RSVPed events to a member's Google Calendar.
- **Invitation-to-ticket conversion** — auto-generating a ticket for a member who RSVPed when native ticketing is enabled. Requires Finance integration.
- **Capacity-aware invitations** — declining new acceptances when an event is at capacity. Overlaps with ticket capacity.
- **Platform invitation consolidation** — merging `app/Models/Invitation` into the support module if the two models prove redundant over time.

---

## Open questions

None blocking. Design is specified; implementation can begin.
