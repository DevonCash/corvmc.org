# Event Management Workflows

This document describes the event management workflows for staff members.

## Event Lifecycle Overview

```
                    ┌─────────────┐
                    │   CREATE    │
                    │  (Wizard)   │
                    └──────┬──────┘
                           │
                           ▼
                    ┌─────────────┐
         ┌─────────│  SCHEDULED  │─────────┐
         │         └──────┬──────┘         │
         │                │                │
         │ Reschedule     │ Cancel         │ (At Capacity)
         │ (TBA)          │                │
         │                │                │
         ▼                ▼                ▼
  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐
  │  POSTPONED  │  │  CANCELLED  │  │ AT CAPACITY │
  │ (read-only) │  │(reschedule) │  │  (active)   │
  └──────┬──────┘  └──────┬──────┘  └─────────────┘
         │                │
         │ Reschedule     │ Reschedule
         │ (new date)     │ (new date required)
         ▼                ▼
  ┌─────────────────────────┐
  │       NEW EVENT         │
  │      (Scheduled)        │
  │ [copies performers,     │
  │  tags, poster, times]   │
  └─────────────────────────┘
```

---

## Staff Dashboard

**Location:** `/staff/events`

### Tabs

| Tab | Shows | Sort |
|-----|-------|------|
| **Upcoming** | Future active events (Scheduled/At Capacity) | Date ascending |
| **Past** | Past events (all statuses) | Date descending |
| **All** | All events | Date descending |

### Header Actions

- **Venues** - Link to manage venues
- **New Event** - Open wizard modal to create event

---

## Creating an Event

Events are created via the **New Event** button on the Events list page. A wizard guides you through the process.

### Step 1: Basic Info

| Field | Required | Notes |
|-------|----------|-------|
| Title | Yes | Event name (max 255 characters) |
| Start Date/Time | Yes | When the event begins |
| End Time | CMC only | Required for CMC venue to check space availability |
| Venue | Yes | Defaults to CMC; can create new venues inline |

**After validation:** The system automatically checks for space conflicts if the venue is CMC.

### Step 2: Space Reservation (CMC venues only)

This step only appears when the venue is the CMC practice space.

**Conflict Detection checks for:**
- Existing rehearsal reservations (shows who reserved)
- Other events at CMC
- Space closures (shows closure reason)

**Conflict States:**

| State | Icon | Meaning | Action |
|-------|------|---------|--------|
| Available | Green checkmark | No conflicts | Reservation will be created |
| Setup Conflict | Yellow warning | Conflicts only in setup/teardown buffer | Warning shown; can adjust times or proceed |
| Event Conflict | Red alert | Conflicts during event time | Non-admins blocked; admins can override |

**Setup & Teardown Times:**
- Default: 30 minutes each (configurable in Organization Settings)
- Adjustable per-event in this step
- These buffer times are added to the space reservation

**Admin Override:**
Only visible to admin users. Allows forcing creation even when conflicts exist during event time.

### Step 3: Confirmation

- Summary of event details (title, date/time, venue)
- Space reservation status and reserved time period
- Click "Create" to finish

---

## Editing an Event

### What You CAN Edit

| Field | Notes |
|-------|-------|
| Title | Event name |
| Description | Rich text editor |
| Doors Time | When doors open (independent of event start) |
| Poster | Upload event poster image |
| Ticketing | Toggle CMC ticketing, set prices/quantities |
| Event Link | External ticketing or info URL |
| Published Date | When event becomes public |

### What You CANNOT Edit (Requires Reschedule)

| Field | Why |
|-------|-----|
| Start Date/Time | Ensures conflict checking and event history |
| End Time | Affects space reservation duration |
| Venue | May change from CMC to external (affects reservations) |

**Helper text** on these fields explains: "Use the Reschedule action to change the date/time" or "Use the Reschedule action to change the venue"

### Read-Only Events

**Cancelled** and **Postponed** events are fully read-only:
- All form fields disabled
- Subheading shows: "This event is [Status] and cannot be edited."
- Save button hidden

**Exception:** Cancelled events can still be rescheduled (see below).

### Space Reservation on Edit

For CMC events, click the **gear icon** next to the Venue field to:
- View current setup/teardown times
- Adjust setup/teardown times
- Force override conflicts (admin only)

---

## Rescheduling an Event

The **Reschedule** action is available for:
- Scheduled events (can use TBA mode or new date)
- Cancelled events (must provide new date)

### Mode 1: Postpone to TBA

*Not available for cancelled events*

When the new date is unknown:
1. Keep "New date is known" toggle OFF
2. Enter a reason (optional)
3. Confirm

**Result:**
- Event status → Postponed
- Event becomes read-only
- Event is unpublished

### Mode 2: Reschedule to New Date

When you know the new date:
1. Toggle "New date is known" ON
2. Enter the new start date/time
3. Enter the new end time (required for CMC venues)
4. Enter a reason (optional)
5. Review space conflicts (for CMC venues)
6. Confirm

**Result:**
- Original event status → Postponed (or stays Cancelled if was cancelled)
- Original event is unpublished
- Original event links to new event via `rescheduled_to_id`
- **NEW event is created** with:

### What Gets Copied to the New Event

| Copied | Details |
|--------|---------|
| Title | Same title |
| Description | Full description text |
| Venue | Same venue |
| All Performers | With same order and set lengths |
| All Tags | Genre tags |
| Poster Image | Copied to new event |
| Ticketing Settings | CMC ticketing, prices, quantities |
| Event Link | External URLs |
| Organizer | Same organizer |
| Visibility | Same visibility setting |

**Time Offsets Preserved:**
- If doors were 30 min before start → doors will be 30 min before new start
- If event was 3 hours long → new event will be 3 hours long (unless you specify different end time)

---

## Cancelling an Event

The **Cancel** action is available for active events (Scheduled or At Capacity).

### Process

1. Click "Cancel Event"
2. Enter a reason (optional but recommended)
3. Confirm

### Result

- Event status → Cancelled
- Event becomes read-only
- Event excluded from public listings
- **Reschedule action remains available** (with new date required)

### Recovery

Cancelled events CAN be rescheduled:
1. Click **Reschedule** on the cancelled event
2. "New date is known" is automatically ON and locked
3. Enter the new date
4. Complete the wizard

A new event is created, linked to the cancelled original.

---

## Publishing an Event

Events have two independent states:

| Concern | Values | Controlled By |
|---------|--------|---------------|
| **Status** | Scheduled, Cancelled, Postponed, At Capacity | Actions (Reschedule, Cancel) |
| **Publication** | Draft, Scheduled, Published | `published_at` field |

### Publication States

| State | `published_at` | Visible to Public |
|-------|----------------|-------------------|
| Draft | NULL | No |
| Scheduled | Future date | No (until that date) |
| Published | Past date | Yes |

### To Publish Immediately

1. Click the **Publish** action (header button)
2. Confirm

The event becomes immediately visible to the public.

### To Schedule Publication

1. Edit the event
2. Set "Publish At" to a future date/time
3. Save

The event will automatically become visible when that time arrives.

### Auto-Unpublish on Reschedule

When you reschedule an event:
- Original event is **unpublished**
- New event starts as **draft**
- You must publish the new event separately

---

## Performers Management

### Adding Performers

On the event edit page, use the **Performers** relation manager:

1. Click "Attach Performer"
2. Search for and select a band
3. Set order (display order in lineup)
4. Set set length (minutes, optional)
5. Save

### Performer Order

Performers are displayed in order by their `order` field. Use drag-and-drop or edit the order number to rearrange.

### When Rescheduling

All performers are automatically copied to the new event with the same order and set lengths.

---

## Ticketing

### CMC Ticketing

Toggle **CMC Ticketing** ON to enable built-in ticket sales:

| Field | Notes |
|-------|-------|
| Tickets Available | Leave blank for unlimited |
| Ticket Price Override | Default price from settings if blank |
| Tickets Sold | Read-only counter |

### External Ticketing

With CMC Ticketing OFF:

| Field | Notes |
|-------|-------|
| Event Link | URL to external ticket site or event page |
| Ticket Price | Display price (informational) |
| NOTAFLOF | "No One Turned Away For Lack of Funds" flag |

---

## Space Reservation Integration

For events at the CMC venue, a space reservation is automatically managed.

### Reserved Period

The reservation blocks the space for:
```
[Setup Time] + [Event Duration] + [Teardown Time]
```

Example: Event 7-10 PM with 30 min setup/teardown
→ Space reserved 6:30 PM - 10:30 PM

### Conflict Types

| Type | Description | Resolution |
|------|-------------|------------|
| **Rehearsal Reservation** | Another member has booked practice time | Shows who reserved and times |
| **Other Event** | Another event at CMC | Shows event title |
| **Space Closure** | Maintenance, holiday, etc. | Shows closure reason |

### Conflict Display

When conflicts are detected, the wizard shows:
- **Who** has the conflicting reservation (e.g., "John Doe")
- **When** the conflict occurs (e.g., "2:00 PM - 5:00 PM")
- **Type** of conflict (Reservation, Event, Closure)

---

## Quick Reference: Common Tasks

### "I need to change an event's time"

Use **Reschedule** action:
1. Edit page → Reschedule button
2. Toggle "New date is known" ON
3. Enter new date/time
4. Complete wizard

A new event is created; original becomes Postponed.

### "I accidentally cancelled an event"

Use **Reschedule** on the cancelled event:
1. Edit page → Reschedule button (still available)
2. Enter the correct date/time
3. Complete wizard

A new event is created; original stays Cancelled but links to new event.

### "There's a conflict but I need to create the event anyway"

Admin override (admin users only):
1. In Space Reservation step, toggle "Override conflicts"
2. Proceed with creation

The event and reservation are created despite conflicts.

### "I need to update just the setup/teardown time"

1. Edit the event
2. Click gear icon next to Venue field
3. Adjust setup/teardown minutes
4. Click action button to update reservation

### "Event is sold out, should I change status?"

Use **At Capacity** status (not currently exposed in UI). For now:
1. Edit ticketing to show 0 remaining
2. Or update ticket quantity to match sold

---

## Quick Reference: Status Transitions

| Current Status | Available Actions |
|----------------|-------------------|
| Scheduled | Edit, Publish, Reschedule, Cancel, Delete |
| At Capacity | Edit, Publish, Cancel, Delete |
| Postponed | View only, Reschedule (new date), Delete |
| Cancelled | View only, Reschedule (new date required), Delete |

---

## Permissions

| Action | Required Permission |
|--------|---------------------|
| View events list | Staff panel access |
| Create events | Staff panel access |
| Edit events | Staff panel access (own) or `production manager` (any) |
| Publish events | `publish` policy check |
| Reschedule events | `reschedule` policy check |
| Cancel events | `cancel` policy check |
| Delete events | `delete` policy check |
| Override conflicts | `admin` role |

---

## Known Limitations

1. **Venue/time changes require rescheduling** - You cannot directly edit start time, end time, or venue. This ensures proper conflict checking and maintains event history.

2. **Cancelled events require a new date** - When rescheduling a cancelled event, you must provide a new date. The "Postpone to TBA" option is not available.

3. **Publication is separate from status** - An event can be "Scheduled" (status) but not yet "Published" (visibility). Remember to publish your events when ready.

4. **No automatic attendee notifications** - Cancelling or rescheduling does not automatically notify ticket holders. This must be done manually.

5. **Space reservation sync on edit may fail silently** - If you edit an event and the space reservation can't be updated due to new conflicts, the event saves but shows a warning. Review warnings carefully.
