# Band Reservations: Deferred Payment Flow

## Goal

Allow any band member to schedule a rehearsal, but defer credit/payment to whoever chooses to pay. This prevents using someone's credits without their consent.

## Current vs Proposed Flow

| Step | Current | Proposed |
|------|---------|----------|
| Creation | Creator's credits calculated & deducted | No credits calculated, reservation in `Scheduled` status |
| Charge | Created immediately with creator's pricing | Not created until payment |
| Payment | Creator pays (or uses their credits) | Any band member can pay using their own credits |
| Confirmation | Auto-confirms if < 3 days away | Confirms when someone pays |

Note: Uses existing `Scheduled` → `Confirmed` status flow, not a new "Awaiting Payment" status.

## Implementation

### 1. Modify CreateBandReservation Page

**File:** `app/Filament/Band/Resources/BandReservationsResource/Pages/CreateBandReservation.php`

Changes:
- Remove cost calculation during creation (the `calculateCost()` method calls)
- Create reservation with `Scheduled` status (existing flow)
- Pass `deferChargeCreation: true` to skip charge creation entirely
- Update UI to show "Payment needed" instead of cost breakdown

The reservation form should:
- Only collect date/time (no cost display at creation)
- Show confirmation that any band member can pay later
- Create reservation without triggering `HandleChargeableCreated`

### 2. Modify CreateReservation Action

**File:** `app-modules/space-management/src/Actions/Reservations/CreateReservation.php`

Add support for `deferChargeCreation` parameter:
- When true, dispatch `ReservationCreated` event with a flag to skip charge creation
- The reservation is created but no `Charge` record is made

### 3. Modify HandleChargeableCreated Listener

**File:** `app-modules/finance/src/Listeners/HandleChargeableCreated.php`

Check for `deferChargeCreation` flag on the event and skip charge creation if set.

### 4. Add PayBandReservation Action

**File (new):** `app/Filament/Band/Resources/BandReservationsResource/Actions/PayBandReservationAction.php`

A Filament Action that:
1. Shows the paying user's credit balance and calculated cost
2. Creates a `Charge` record with the paying user's credits applied
3. Handles payment (Stripe checkout or credits-only)
4. Confirms the reservation on successful payment
5. Sends confirmation notifications

### 5. Update ListBandReservations Page

**File:** `app/Filament/Band/Resources/BandReservationsResource/Pages/ListBandReservations.php`

Add:
- "Pay" action button for reservations in `Scheduled` status (that have no charge)
- Visual indicator for reservations needing payment
- Show who created the reservation

### 6. Update band-reservation-summary View

**File:** `resources/views/filament/band/components/band-reservation-summary.blade.php`

Remove cost display during creation since it's now calculated at payment time.

## Key Design Decisions

1. **No charge at creation** - Cleaner than creating a placeholder charge that gets recalculated
2. **Cost calculated for paying user** - They see their own credits/sustaining status applied
3. **Any active band member can pay** - Checked via `$band->activeMembers()`
4. **Uses existing status flow** - `Scheduled` → `Confirmed` (no new statuses)
5. **Unpaid reservations block time** - Scheduled status is active, so the slot is held; manual cleanup required if no one pays

## Files to Modify

| File | Change |
|------|--------|
| `CreateBandReservation.php` | Remove cost calc, defer charge creation |
| `CreateReservation.php` | Add `deferChargeCreation` parameter |
| `HandleChargeableCreated.php` | Skip charge creation when deferred |
| `ListBandReservations.php` | Add Pay action, show payment status |
| `band-reservation-summary.blade.php` | Remove cost display at creation |
| **New:** `PayBandReservationAction.php` | Payment flow with credit calculation |

## Verification

1. **Create band reservation:**
   - Log in as band member
   - Create a reservation - should NOT show cost or deduct credits
   - Reservation appears in list with `Scheduled` status, shows "Payment needed"

2. **Pay as different member:**
   - Log in as different band member (sustaining member with credits)
   - View the pending reservation
   - Click "Pay" - should show THEIR credit balance applied
   - Complete payment

3. **Verify credits:**
   - Original creator's credits: unchanged
   - Paying member's credits: deducted appropriately

4. **Verify reservation:**
   - Status changes to `Confirmed` after payment
   - Charge record shows paying user's credits applied
