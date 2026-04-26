# Invitations — Implementation Plan

Sequenced for a solo developer working through it PR by PR. Each epic groups related work; each task within an epic is roughly one pull request. Tasks within an epic are ordered; epics are ordered by dependency (later epics depend on earlier ones unless noted).

---

## Epic 1: Invitation model, migration, contract, and trait

Foundation. The `support_invitations` table, the `Invitation` model, the `InvitationSubject` contract, and the `HasInvitations` trait. Everything else depends on these.

### 1.1 Create the Invitation model and migration

New table `support_invitations`:

```
id
inviter_id       integer, nullable, FK → users
user_id          integer, FK → users
invitable_type   string
invitable_id     integer
status           string — 'pending', 'accepted', 'declined'
data             JSON, nullable
responded_at     timestamp, nullable
timestamps
```

Unique constraint on `(user_id, invitable_type, invitable_id)`.

Indexes: `(invitable_type, invitable_id, status)`, `(user_id, status)`.

Model at `app-modules/support/src/Models/Invitation.php`. Casts: `data` as `array`, `responded_at` as `datetime`. Relationships: `inviter()` belongsTo User (nullable), `user()` belongsTo User, `invitable()` morphTo.

Add `'support_invitation' => \CorvMC\Support\Models\Invitation::class` to the morph map in `AppServiceProvider`. Use `support_invitation` (not `invitation`) to avoid collision with the existing platform sign-up morph alias.

Factory at `app-modules/support/database/factories/InvitationFactory.php` with states for each status.

**Test:** Migration runs, model creates/reads correctly, unique constraint rejects duplicate `(user_id, invitable_type, invitable_id)` combinations. Factory produces valid records.

### 1.2 Create the InvitationSubject contract and HasInvitations trait

Contract at `app-modules/support/src/Contracts/InvitationSubject.php`:

```php
interface InvitationSubject
{
    public function acceptsInvitations(): bool;
    public function eligibleUsers(): ?Collection;
    public function allowsSelfInvite(): bool;
    public function onInvitationAccepted(Invitation $invitation): void;
    public function onInvitationDeclined(Invitation $invitation): void;
}
```

Trait at `app-modules/support/src/Concerns/HasInvitations.php`:

- `invitations()` — morphMany Invitation.
- `pendingInvitations()` — scoped to status `pending`.
- `acceptedInvitations()` — scoped to status `accepted`.
- `declinedInvitations()` — scoped to status `declined`.

**Test:** A model using `HasInvitations` can create and query invitations through the morph relationship.

### 1.3 Create domain events

Under `app-modules/support/src/Events/`:

- `InvitationCreated` — carries Invitation.
- `InvitationAccepted` — carries Invitation.
- `InvitationDeclined` — carries Invitation.

Simple event classes with a public `$invitation` property.

---

## Epic 2: InvitationService

Core operations. The service handles create, respond, prompt-group, and retract — the shared logic that all invitation types use.

### 2.1 Create InvitationService and facade

Service at `app-modules/support/src/Services/InvitationService.php`. Facade at `app-modules/support/src/Facades/InvitationService.php`. Register singleton in `SupportServiceProvider`.

Methods:

**`invite(InvitationSubject $subject, User $invitee, ?User $inviter = null, array $data = []): Invitation`**
- Guards: `$subject->acceptsInvitations()` must be true, `$invitee` must be in `$subject->eligibleUsers()` (if non-null), if `$inviter` is null then `$subject->allowsSelfInvite()` must be true.
- Creates Invitation with status `pending` (or `accepted` immediately for self-invites — see below).
- Fires `InvitationCreated`.
- For self-invites where `allowsSelfInvite()` is true and no inviter: creates with `status=accepted`, `responded_at=now()`. Fires both `InvitationCreated` and `InvitationAccepted` so listeners keyed on acceptance (current or future) work consistently. This covers the event RSVP case where clicking "Going" is a single action.

**`accept(Invitation $invitation): void`**
- Guards: status must be `pending` or `declined`.
- Transitions to `accepted`, sets `responded_at`.
- Calls `$invitation->invitable->onInvitationAccepted($invitation)`.
- Fires `InvitationAccepted`.

**`decline(Invitation $invitation): void`**
- Guards: status must be `pending` or `accepted`.
- Transitions to `declined`, sets `responded_at`.
- Calls `$invitation->invitable->onInvitationDeclined($invitation)`.
- Fires `InvitationDeclined`.

**`promptGroup(InvitationSubject $subject, User $inviter, ?Collection $excludeUsers = null): Collection`**
- Creates a pending Invitation for each eligible user not already invited and not in `$excludeUsers`. Returns the created Invitations.
- Used by rehearsal attendance: band admin prompts all members at once.

**`retract(Invitation $invitation): void`**
- Deletes a pending invitation (inviter retracts before invitee responds).
- Guards: status must be `pending`.

**Test:**
- `invite()` creates correct record, fires event, respects guards.
- Self-invite creates with `accepted` status and `responded_at` set.
- `accept()` transitions status, calls `onInvitationAccepted`, fires event.
- `decline()` transitions status, calls `onInvitationDeclined`, fires event.
- `accept()` from `declined` works (change of mind).
- `decline()` from `accepted` works (change of mind).
- `promptGroup()` creates invitations for all eligible users, skips already-invited.
- `retract()` deletes pending invitation, rejects non-pending.
- Guards reject: closed subject, ineligible user, self-invite when disallowed.

---

## Epic 3: Band module integration

Wire `Band` as an `InvitationSubject`, migrate existing invited BandMember rows, and update `BandService`.

### 3.1 Implement InvitationSubject on Band

In `app-modules/bands/src/Models/Band.php`:

- Add `implements InvitationSubject` and `use HasInvitations`.
- `acceptsInvitations()`: return `$this->status === 'active'` (the `band_profiles.status` column defaults to `'active'`, added in migration `2025_09_10_191238`).
- `eligibleUsers()`: all users not already active members of this band.
- `allowsSelfInvite()`: return `false`.
- `onInvitationAccepted(Invitation $invitation)`: attach user to `members()` pivot with role and position from `$invitation->data`.
- `onInvitationDeclined(Invitation $invitation)`: no-op.

Update `Band::pendingInvitations()` to use the `HasInvitations` trait version (queries `support_invitations`) instead of the current `memberships()->invited()`.

**Test:** Band implements contract correctly. Accepting an invitation creates a BandMember pivot entry. Declining does nothing to the pivot.

### 3.2 Update BandService to use InvitationService

Modify `BandService::inviteMember()` to create an `Invitation` via `InvitationService::invite()` instead of creating a BandMember with `status='invited'`. Pass role and position in the `data` array.

Update `BandService::acceptInvitation()` to call `InvitationService::accept()` (which triggers `Band::onInvitationAccepted()`).

Update `BandService::declineInvitation()` to call `InvitationService::decline()`.

Remove `BandService::cancelInvitation()` — replace with `InvitationService::retract()`.

`BandService::addMember()` stays unchanged — direct add without invitation flow.

**Test:** `inviteMember()` creates a support_invitation, not a BandMember row. `acceptInvitation()` creates the BandMember pivot. `declineInvitation()` leaves no pivot entry.

### 3.3 Migrate existing invited BandMember data and drop columns

Two separate migrations (so the backfill and the schema change are independently re-runnable):

**Migration 1 — backfill:** For each `BandMember` where `status='invited'`: create a row in `support_invitations` with `invitable_type='band'`, `invitable_id=band_profile_id`, `user_id`, `status='pending'`, `data={'role': role, 'position': position}`, `created_at=invited_at`. Delete the migrated `BandMember` rows (they were invitation placeholders, not real memberships).

**Migration 2 — drop columns:** Drop the `status` column and the `invited_at` column from `band_profile_members`. Note: `BandService::create()` and `addMember()` reference a `joined_at` column that doesn't exist in the schema — this is a pre-existing bug unrelated to this plan, but verify it doesn't surface during migration testing.

Remove the `status` and `invited_at` entries from `BandMember::$fillable` and `$casts`. Remove the `invited()` scope. Remove the `active()` scope if no longer referenced (all rows are active by definition). Remove the `invited()` state from `BandMemberFactory`. Update BandMember docblock to remove stale `@method` and `@property` annotations. Update `Band::activeMembers()` — can simplify to just `memberships()` since all members are active, or keep for semantic clarity.

**Test:** Migration converts invited rows to Invitation records with correct data. Active BandMember rows are untouched. Columns are dropped. Existing tests still pass after removing references to the old pattern.

---

## Epic 4: Event module integration

Wire `Event` as an `InvitationSubject` for RSVP.

### 4.1 Implement InvitationSubject on Event

In `app-modules/events/src/Models/Event.php`:

- Add `implements InvitationSubject` and `use HasInvitations`.
- `acceptsInvitations()`: published (`$this->isPublished()` from the `HasPublishing` trait — checks both that `published_at` is set and in the past, which correctly excludes scheduled-but-not-yet-published events), future (`start_datetime->isFuture()`), and status is `Scheduled`.
- `eligibleUsers()`: return `null` (any authenticated member).
- `allowsSelfInvite()`: return `true`.
- `onInvitationAccepted(Invitation $invitation)`: no-op.
- `onInvitationDeclined(Invitation $invitation)`: no-op.

**Test:** Published future event accepts invitations. Past, cancelled, unpublished events reject. Self-invite creates an accepted invitation immediately.

---

## Epic 5: SpaceManagement module integration

Wire `RehearsalReservation` as an `InvitationSubject` for rehearsal attendance.

### 5.1 Implement InvitationSubject on RehearsalReservation

In `app-modules/space-management/src/Models/RehearsalReservation.php`:

- Add `implements InvitationSubject` and `use HasInvitations`.
- `acceptsInvitations()`: reservable is a Band (check `reservable_type === 'band'`) and `reserved_at` is in the future.
- `eligibleUsers()`: the band's active members. `activeMembers()` returns a HasMany of `BandMember`, not User, so load the relationship: `$this->reservable->activeMembers()->with('user')->get()->pluck('user')`.
- `allowsSelfInvite()`: return `false`.
- `onInvitationAccepted(Invitation $invitation)`: no-op.
- `onInvitationDeclined(Invitation $invitation)`: no-op.

**Test:** Band rehearsal reservation accepts invitations. Non-band or past reservations reject. Eligible users are the band's active members only.

---

## Epic 6: InvitationPolicy

Authorization rules. Depends on all three subject implementations being in place so the policy can reason about invitable types.

### 6.1 Create InvitationPolicy

Policy at `app/Policies/InvitationPolicy.php`. Auto-discovered via `Gate::guessPolicyNamesUsing()` in `AppServiceProvider` — no manual registration needed.

Methods:

- **`create(User $user, InvitationSubject $subject)`**: For self-invite (events): any authenticated member when `$subject->acceptsInvitations()`. For band invitations: user is owner or admin of the band. For rehearsal attendance prompts: user is owner or admin of the reservation's band.
- **`respond(User $user, Invitation $invitation)`**: The invitee (`$invitation->user_id === $user->id`). Also allow band admin to manually update rehearsal attendance status.
- **`view(User $user, Invitation $invitation)`**: The invitee, the inviter, staff, or band admin/owner when the invitable is their band.
- **`viewAny(User $user, InvitationSubject $subject)`**: Staff (for Filament), band admin/owner (for their band's invitations).
- **`retract(User $user, Invitation $invitation)`**: The inviter, or band admin/owner for band invitations.

**Test:** Each policy method tested for permitted and denied cases. Band admin can invite to their band but not another band. Invitee can respond to their own invitation but not someone else's. Staff can view all. Members can only view their own.

---

## Epic 7: Filament — Band panel updates

Update band-related Filament components to use the Invitation model.

### 7.1 Update SendBandMemberInvitationAction

In `app/Filament/Actions/Bands/SendBandMemberInvitationAction.php`:

- Keep the same form (user select, role, position).
- Change the action handler to call `InvitationService::invite()` with the Band as subject, passing role and position in the data array.
- Trigger notification dispatch (see Epic 9).

**Test:** Action creates an Invitation record, not a BandMember row.

### 7.2 Update AcceptBandInvitationAction and DeclineBandInvitationAction

In `app/Filament/Actions/Bands/AcceptBandInvitationAction.php`:

- Change type check from `BandMember` to `Invitation`.
- Visibility: `$record->status === 'pending'` (on the Invitation).
- Action: call `InvitationService::accept()`.

In `app/Filament/Actions/Bands/DeclineBandInvitationAction.php`:

- Same pattern — check Invitation status, call `InvitationService::decline()`.

Both actions update their `authorize` calls to use `InvitationPolicy`.

**Test:** Actions work on Invitation records. Accept creates BandMember pivot (via `onInvitationAccepted`). Decline doesn't.

### 7.3 Update PendingBandInvitationsWidget

In `app/Filament/Member/Resources/Bands/Widgets/PendingBandInvitationsWidget.php`:

- Change query from `BandMember::where('status', 'invited')` to `Invitation::where('invitable_type', 'band')->where('status', 'pending')->where('user_id', $user->id)`.
- Update columns to pull band info via `$record->invitable` instead of `$record->band`.
- Role and position come from `$record->data['role']` and `$record->data['position']`.
- `invited_at` becomes `$record->created_at`.
- Visibility check uses the same Invitation query.

**Test:** Widget displays pending band invitations from the Invitation model. Accept/decline actions still work.

### 7.4 Update AcceptBandInvitationPage

In `app/Filament/Member/Pages/AcceptBandInvitationPage.php`:

- If this is a legacy redirect page, update the redirect target if needed. The page should resolve the invitation from the Invitation model rather than BandMember.
- If other pages or routes reference band invitation acceptance, update them to query Invitation.

**Test:** Old invitation links still resolve correctly.

---

## Epic 8: Filament — Event and User resources

Add invitation UI to event and user management in Filament.

### 8.1 Create InvitationsRelationManager (shared)

Shared relation manager at `app/Filament/Shared/RelationManagers/InvitationsRelationManager.php`.

Table columns: member name (via `user` relationship), status (badge — color-coded), comment from `data.comment`, `responded_at`. Filterable by status.

Reusable across EventResource, UserResource, and potentially BandResource.

### 8.2 Add invitation section to EventResource

In the EventResource view page:

- Infolist section: "RSVPs" panel with accepted count and declined count as stat entries.
- Attach `InvitationsRelationManager` to show the full table of RSVPs.
- Visible to staff only.

**Test:** Event view page shows RSVP counts and invitation table. Counts update when invitations change status.

### 8.3 Add InvitationsRelationManager to UserResource

Attach the shared `InvitationsRelationManager` to the UserResource. Shows all invitations for the user across all invitable types. Read-only for staff context.

**Test:** User view page shows invitation history spanning events, bands, and rehearsals.

---

## Epic 9: Notifications

### 9.1 Update BandInvitationNotification

In `app-modules/membership/src/Notifications/BandInvitationNotification.php`:

- Constructor now accepts an `Invitation` instead of `Band` + `role` + `position`.
- Pull band, role, position from the Invitation's `invitable` and `data`.
- Action link points to the same acceptance URL.
- Mail + database channels unchanged.

Dispatch from `SendBandMemberInvitationAction` after creating the invitation (or via a listener on `InvitationCreated` that checks `invitable_type === 'band'`).

**Test:** Notification sent on band invitation creation. Content matches: band name, role, accept/decline links.

### 9.2 Create RehearsalAttendanceRequestedNotification

New notification at `app-modules/membership/src/Notifications/RehearsalAttendanceRequestedNotification.php` (or in the integration layer).

Constructor accepts an `Invitation`. Content: "{Band name} has a rehearsal on {date} at {time}. Can you make it?" with Going / Not Going action links.

Mail + database channels.

Dispatched when `InvitationCreated` fires with `invitable_type === 'rehearsal_reservation'`.

**Test:** Each band member gets a notification when the admin prompts attendance. Content includes correct band name, date, time.

### 9.3 Create RehearsalReminderNotification and scheduled command

Notification at `app-modules/membership/src/Notifications/RehearsalReminderNotification.php`.

Sent 24 hours before a reservation to members with `status=pending` who haven't responded.

Scheduled command (or job dispatched daily): query `support_invitations` where `invitable_type='rehearsal_reservation'`, `status='pending'`, and the invitable's `reserved_at` is between now+23h and now+25h. Send reminder. Track via a `reminded_at` key in `data` to avoid re-sending.

Register in `routes/console.php` (Laravel 11 style — no Console Kernel).

**Test:** Reminder sent to pending members 24h before rehearsal. Already-reminded members not re-notified. Accepted/declined members not notified.

---

## Epic 10: Event RSVP UI (member-facing)

### 10.1 Create RSVP component for event pages

Livewire component (or inline Filament action on the member-facing event view) that shows the member's current RSVP status:

- No invitation yet → "RSVP" button.
- Accepted → "Going" (highlighted), with "Not going" toggle.
- Declined → "Not going" (highlighted), with "Going" toggle.

Clicking "RSVP" or "Going" calls `InvitationService::invite()` as a self-invite (creates with `accepted` status immediately). Toggling calls `InvitationService::accept()` or `InvitationService::decline()`.

Show aggregate count: "X going" (count of accepted invitations for this event). Don't show other members' individual responses.

Optional comment field stored in `data.comment`.

**Test:** Member can RSVP to a published future event. Toggle between going/not going. Aggregate count updates. Can't RSVP to past or cancelled events.

---

## Epic 11: Band panel — rehearsal attendance

### 11.1 Create "Request attendance confirmation" action

On the reservation detail view in the Band panel:

- Action button: "Request attendance confirmation."
- Calls `InvitationService::promptGroup()` with the `RehearsalReservation` as subject, the band admin as inviter, excluding the admin themselves.
- Idempotent: re-triggering creates invitations only for members who don't have one yet.
- Dispatches `RehearsalAttendanceRequestedNotification` to each new invitee.

**Test:** Action creates pending invitations for all active band members except the admin. Re-triggering doesn't duplicate. New members added after first prompt get invited on re-trigger.

### 11.2 Create attendance roster on reservation detail

On the reservation detail view in the Band panel:

- Table or infolist showing each band member's attendance status: name, status badge (accepted/declined/pending), comment from `data`.
- Summary line: "X going, Y not going, Z pending."
- Admin can manually update a member's status (e.g., member responded via text) using inline actions that call `InvitationService::accept()` or `InvitationService::decline()`.

**Test:** Roster shows correct counts. Manual status update works. Summary line matches actual counts.

---

## Epic 12: Test cleanup

Independent of other epics after Epic 3. Can be done anytime.

### 12.1 Update tests referencing old invitation pattern

Search for tests that create `BandMember` with `status='invited'` and update them to use `InvitationService::invite()` or the Invitation factory instead. Remove any tests that test the old `BandMember.status='invited'` lifecycle.

**Test:** Full test suite passes with no references to `BandMember::status='invited'`.

---

## Dependency graph

```
Epic 1 (model, migration, contract, trait)
  └─▶ Epic 2 (InvitationService)
        ├─▶ Epic 3 (band integration + data migration)
        │     ├─▶ Epic 7 (filament band panel updates)
        │     └─▶ Epic 12 (test cleanup) ── independent after 3
        ├─▶ Epic 4 (event integration)
        │     └─▶ Epic 10 (event RSVP UI)
        ├─▶ Epic 5 (rehearsal integration)
        │     └─▶ Epic 11 (rehearsal attendance UI)
        └─▶ Epic 6 (InvitationPolicy)
              └─▶ Epic 8 (filament event + user resources)

Epic 9 (notifications) ── requires Epics 3+5, independent of 6-8
```

Epics 3, 4, and 5 are independent of each other (all three implement `InvitationSubject` on different models). Epic 6 can start after any one of them but covers all three. Epics 7, 10, and 11 are the UI work for each subject type and can proceed in parallel once their respective integration epic and Epic 6 are done.

---

## Smoke tests

**Band invitation end-to-end:** Band admin invites a member → member receives notification → member accepts → BandMember pivot entry created with correct role and position → member appears in band's active members → invitation record shows `accepted` status with `responded_at` set.

**Event RSVP end-to-end:** Member visits published future event → clicks "Going" → invitation created with `accepted` status → aggregate count shows "1 going" → member toggles to "Not going" → status changes to `declined` → count drops to "0 going" → staff sees both the count and the individual record in Filament.

**Rehearsal attendance end-to-end:** Band admin creates a rehearsal reservation → clicks "Request attendance confirmation" → each band member (except admin) gets a pending invitation and a notification → member clicks "Going" → status changes to `accepted` → roster shows updated count → 24h before rehearsal, pending members get a reminder.

---

## Out of scope for this plan

- **Public RSVP counts on events** — showing "23 going" on the public event page.
- **Guest RSVPs** — non-members RSVPing to public events via email.
- **Volunteer shift invitations** — using invitations for the volunteering module's sign-up flow.
- **Calendar integration** — adding RSVPed events to Google Calendar.
- **Invitation-to-ticket conversion** — auto-generating tickets from RSVPs.
- **Capacity-aware invitations** — declining new acceptances at capacity.
- **Platform invitation consolidation** — merging `app/Models/Invitation` into the support module.
