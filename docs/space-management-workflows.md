# Space Management Workflows

This document describes the space management workflows for staff members with the "manage practice space" permission.

## Overview

The CMC practice space can be reserved by members for rehearsals. Staff manage reservations, handle conflicts, and maintain space closures.

## Reservation Lifecycle

```
┌─────────────┐
│   CREATE    │
│ (Member or  │
│   Staff)    │
└──────┬──────┘
       │
       ▼
┌─────────────┐     Auto-confirm (< 3 days)
│  SCHEDULED  │─────────────────────────────┐
│  (pending)  │                             │
└──────┬──────┘                             │
       │                                    │
       │ Manual confirm                     │
       │ OR confirmation                    │
       │ reminder                           │
       ▼                                    ▼
┌─────────────┐                      ┌─────────────┐
│  CONFIRMED  │◄─────────────────────│  CONFIRMED  │
└──────┬──────┘                      └─────────────┘
       │
       │ Time passes
       ▼
┌─────────────┐
│  COMPLETED  │
└─────────────┘

Alternative paths:
       │
       │ Cancel (manual or auto)
       ▼
┌─────────────┐
│  CANCELLED  │
└─────────────┘
```

## Staff Dashboard

**Location:** `/staff/space-management`

### Tabs

| Tab | Description | Sort |
|-----|-------------|------|
| **Upcoming** | Reservations from today forward (non-cancelled) | Date ascending |
| **Needs Attention** | Reservations approaching auto-cancel or past unpaid | Date ascending |
| **All** | Complete reservation history | Date descending |

### Header Actions

- **Space Closures** - Link to manage closures
- **Create Reservation** - Open wizard modal to create for any user

---

## Creating a Reservation (Staff)

Staff can create reservations for any member via the modal wizard.

### Step 1: Contact

- Select the user (searchable dropdown)
- Phone number is auto-filled from user's profile

### Step 2: Schedule

| Field | Required | Notes |
|-------|----------|-------|
| Date | Yes | Must be at least tomorrow |
| Start Time | Yes | Dropdown of available times (9 AM - 10 PM) |
| End Time | Yes | 1-8 hour duration limit |
| Notes | No | Optional notes about the reservation |

**Conflict Detection:**
Times that conflict with other reservations, events, or closures are automatically excluded from the dropdowns.

### Step 3: Confirmation

- Shows calculated cost
- Shows free hours used (if sustaining member)
- Summary of reservation details

**Staff-created reservations default to `Confirmed` status.**

---

## Reservation Statuses

| Status | Display | Meaning |
|--------|---------|---------|
| Scheduled | "Scheduled" | Awaiting confirmation (future reservation) |
| Reserved | "Reserved" | Recurring instance awaiting confirmation |
| Confirmed | "Confirmed" | Ready to use, credits deducted |
| Cancelled | "Cancelled" | Cancelled by user or staff |
| Completed | "Completed" | Past reservation (auto-marked) |

### Auto-Confirmation Rules

- **< 3 days away:** Auto-confirmed immediately at creation
- **3-7 days away:** Scheduled, needs manual confirmation or reminder
- **> 7 days away:** Scheduled, reminder sent closer to date

### Auto-Cancellation

Reservations that aren't confirmed within 24 hours of their reminder are automatically cancelled.

---

## Editing Reservations

### What Can Be Edited

- Date
- Start Time
- End Time
- Notes
- Status (staff only)

### What Cannot Be Edited

- Reservations marked as Paid, Comped, or Refunded
  - Staff should cancel and recreate instead
- Past reservations

### Staff Override

Staff with `manage practice space` permission can change the status via the Admin Controls section in the edit form.

---

## Confirming Reservations

### Confirm Action

Available when:
- Status is Scheduled or Reserved
- Reservation is within 5 days (anti-spam rule)

**Effect:**
- Status changes to Confirmed
- Credits deducted from user's account
- Confirmation notification sent

### Bulk Confirm

Select multiple reservations in the table and use the bulk action to confirm all at once.

---

## Cancelling Reservations

### Cancel Action

Available when:
- Reservation is active (not already cancelled/completed)
- Reservation is in the future

**Options:**
- Add cancellation reason (optional but recommended)

**Effect:**
- Status changes to Cancelled
- Credits refunded to user's account
- Cancellation notification sent

### Bulk Cancel

Select multiple reservations in the table and use the bulk action.

---

## Space Closures

**Location:** `/staff/space-management` → "Space Closures" button

### Creating a Closure

| Field | Required | Notes |
|-------|----------|-------|
| Type | Yes | Maintenance, Holiday, Private Event, Weather, Other |
| Starts At | Yes | When the closure begins |
| Ends At | Yes | When the closure ends |
| Notes | No | Additional details about the closure |

**Affected Reservations Preview:**
When you select dates, the form shows any reservations that overlap with the closure period. You can optionally cancel these reservations automatically when creating the closure - members will be notified with the closure type as the reason.

### Closure Types

| Type | Use Case |
|------|----------|
| Maintenance | Repairs, cleaning, equipment work |
| Holiday | Holiday closures |
| Private Event | Private rentals, special events |
| Weather | Weather-related closures |
| Other | Miscellaneous |

### Impact on Reservations

- Members cannot book time slots that overlap with closures
- A **buffer period** (default 30 minutes) is added to each closure
- Existing reservations are NOT automatically cancelled when a closure is created
  - Staff should manually cancel affected reservations

---

## Recurring Reservations

**Location:** `/staff/recurring-reservations` (Admin only)

### Creating a Series

- **User:** Who the series is for
- **Frequency:** Weekly, biweekly, etc. (RRule format)
- **Time Slot:** Start time, end time
- **End Date:** When the series stops generating instances

### Managing Instances

Each instance is a separate reservation that can be:
- Edited individually
- Cancelled individually
- Confirmed individually

**Conflicting instances** are created with status `Cancelled` and reason "Scheduling conflict".

### Pause/Resume

- Pausing stops future instance generation
- Resuming regenerates future instances
- Existing instances are not affected

---

## Payment Handling

### Member Payment Flow

1. Member creates reservation
2. If cost > $0 and within 7 days: redirected to Stripe checkout
3. Payment processed by Stripe
4. Charge marked as Paid

### Staff Actions

| Action | Effect |
|--------|--------|
| **Mark as Paid** | Manually mark reservation as paid (e.g., cash payment) |
| **Mark as Comped** | Comp the reservation (no charge) |

### Free Hours

- Sustaining members get 4 free hours per month
- Free hours are applied first, then remaining hours are charged
- Free hours reset on the 1st of each month

---

## Quick Reference: Common Tasks

### "Member says they can't book a time slot"

1. Check Space Closures - is there a closure during that time?
2. Check existing reservations - is someone else booked?
3. Check events calendar - is there a production?

### "Member wants to change their reservation time"

If the reservation is:
- **Pending/Confirmed:** They can edit it themselves, or staff can edit
- **Paid/Comped:** Must cancel and recreate

### "Need to close the space for maintenance"

1. Go to Space Closures
2. Create closure with type "Maintenance"
3. Manually review and cancel any affected reservations
4. Notify affected members (optional - via notes on cancellation)

### "Recurring member's instance was auto-cancelled"

1. Check why - look at `cancellation_reason`
2. If conflict is resolved, create a new single reservation for that slot
3. Or edit the recurring series if the conflict is permanent

---

## Permissions Reference

| Permission | Capability |
|------------|------------|
| Any member | Create/view/edit/cancel own reservations |
| Sustaining member | + Create recurring reservations |
| `manage practice space` | + View all reservations, create for others, manage closures |
| Admin | + Manage recurring series |

---

## Known Limitations

1. **No automatic closure conflict handling** - Creating a closure doesn't cancel existing reservations. Staff must manually cancel affected reservations.

2. **Recurring instances are independent** - Editing one instance doesn't affect others. There's no "edit all future instances" option.

3. **Paid reservations can't be edited** - Members must cancel and recreate. This protects against payment discrepancies but can be inconvenient.

4. **Status names can be confusing** - "Scheduled" vs "Reserved" are similar. "Scheduled" is for one-time reservations, "Reserved" is for recurring instances.

5. **No waitlist** - If a slot is taken, members must check back later. There's no notification when a slot becomes available.
