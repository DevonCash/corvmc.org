# SMS Notifications Spec — Reservation Lifecycle

## Overview

Add Twilio SMS notifications to the reservation lifecycle using `laravel-notification-channels/twilio`. SMS is opt-in per user and limited to time-sensitive notifications. A `ReservationNotification` base class consolidates shared logic across all reservation notifications.

## Lifecycle Timeline

```
1. Reservation Created
   → Member: ReservationCreatedNotification (explains confirmation process)

2. Confirmation Window Opens (3 days before)
   → Member: ReservationConfirmationNotification (request to confirm + pre-pay nudge)

3. User Confirms (kicks off payment)
   ├─ Online: payment completes immediately → lock code generated
   │  → Member: ReservationConfirmedNotification
   │  → (if first-timer) Member: FirstReservationMemberNotification
   │  → (if first-timer) Staff: FirstReservationStaffNotification
   └─ Cash: payment pending until staff collects
      → Member: ReservationConfirmedNotification
      → (if first-timer) Member: FirstReservationMemberNotification
      → (if first-timer) Staff: FirstReservationStaffNotification

4. Confirmation Window Closes (morning-of, 7:45 AM)
   If not confirmed → auto-cancel
   → Member: ReservationAutoCancelledNotification

5. Morning-of Reminder (8:00 AM, confirmed reservations only)
   → Member: ReservationReminderNotification
     - Online: includes door code
     - Cash: includes push to switch to self-service

6. User Switches Cash → Online (any time after confirmation)
   → Lock code generated
   → Member: door code SMS
   → (if not first-timer) Staff: StaffPresenceNoLongerRequiredNotification
```

### Applicability

Reservations booked less than 3 days out go directly to step 3 (Confirmed) at booking time — they skip steps 1-2 and the auto-cancel path entirely.

## Package

```
composer require laravel-notification-channels/twilio
```

Config in `.env`:

```
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
```

## User opt-in

`phone` already exists on the `users` table. Add `sms_notifications_enabled` to the existing `UserSettingsData` DTO (cast on `$user->settings`):

```php
// app-modules/membership/src/Data/UserSettingsData.php
class UserSettingsData extends Data
{
    public function __construct(
        public bool $sms_notifications_enabled = false,
    ) {}
}
```

SMS channel is only added when both `$notifiable->phone` is set and `$notifiable->settings->sms_notifications_enabled` is true. No migration needed — the `settings` JSON column absorbs the new field via the DTO default.

## Reservation form opt-in

The reservation wizard's Contact step (`ReservationForm::contactStep()`) already collects a phone number and has an `sms_ok` checkbox. Currently `saveContactInfo()` only persists the phone — it ignores `sms_ok`.

Update `saveContactInfo` to also set the user setting:

```php
protected static function saveContactInfo(string $phone, bool $smsOk): void
{
    $user = Auth::user();
    if (! $user) {
        return;
    }

    // Normalize to E.164 for Twilio: strip mask chars, prepend +1
    $user->phone = '+1' . preg_replace('/\D/', '', $phone);
    $user->settings = $user->settings->with(sms_notifications_enabled: $smsOk);
    $user->save();
}
```

And the `afterValidation` callback:

```php
->afterValidation(function (Get $get) {
    $phone = $get('contact_phone');
    if ($phone) {
        static::saveContactInfo($phone, (bool) $get('sms_ok'));
    }
}),
```

The checkbox label should also be updated to make the opt-in explicit:

```php
Checkbox::make('sms_ok')
    ->label('Send me text message reminders about my reservations')
    ->default($user?->settings->sms_notifications_enabled ?? false)
    ->dehydrated(false),
```

## Short redirect route

```php
// routes/web.php
Route::get('/r/{reservation}', function (Reservation $reservation, Request $request) {
    Gate::authorize('view', $reservation);

    $params = ['view' => $reservation->id];

    // ?switch=1 auto-opens the Stripe payment modal on the view page
    if ($request->boolean('switch')) {
        $params['switch'] = 1;
    }

    return redirect()->route('filament.member.resources.reservations.index', $params);
})->name('reservation.short-url')->middleware('auth');
```

URLs: `https://corvmc.org/r/42` (~25 chars) or `https://corvmc.org/r/42?switch=1` for the payment switch flow. `Gate::authorize` ensures users can only access their own reservations.

The reservation view page checks for the `switch` query param and auto-mounts `PayWithStripeAction` modal on load.

## Base class: `ReservationNotification`

**Location:** `app-modules/space-management/src/Notifications/ReservationNotification.php`

```php
<?php

namespace CorvMC\SpaceManagement\Notifications;

use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioSmsMessage;

abstract class ReservationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reservation $reservation
    ) {}

    /**
     * Channels: mail + database always, twilio when user opted in AND subclass defines toTwilio().
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (
            method_exists($this, 'toTwilio')
            && $notifiable->phone
            && $notifiable->settings->sms_notifications_enabled
        ) {
            $channels[] = 'twilio';
        }

        return $channels;
    }

    /**
     * Short date for SMS: "5/15 7:00 PM"
     */
    protected function smsDate(): string
    {
        return $this->reservation->reserved_at->format('n/j g:i A');
    }

    /**
     * Short time range for SMS: "5/15 7-9 PM" or "5/15 11 AM-1 PM"
     */
    protected function smsTimeRange(): string
    {
        $start = $this->reservation->reserved_at;
        $end = $this->reservation->reserved_until;

        if ($start->isSameDay($end)) {
            // Omit meridiem on start if same as end (e.g. "7-9 PM")
            $startFormat = $start->format('A') === $end->format('A')
                ? $start->format('n/j g')
                : $start->format('n/j g A');

            return $startFormat . '-' . $end->format('g A');
        }

        return $start->format('n/j g A') . '-' . $end->format('n/j g A');
    }

    /**
     * Short URL for SMS messages.
     */
    protected function smsUrl(): string
    {
        return route('reservation.short-url', $this->reservation);
    }

    /**
     * Short URL with ?switch=1 for cash→online payment switch.
     */
    protected function smsSwitchUrl(): string
    {
        return route('reservation.short-url', ['reservation' => $this->reservation, 'switch' => 1]);
    }

    /**
     * Full Filament URL for emails.
     */
    protected function viewUrl(): string
    {
        return route('filament.member.resources.reservations.index', ['view' => $this->reservation->id]);
    }

    /**
     * Reservations list URL for emails.
     */
    protected function listUrl(): string
    {
        return route('filament.member.resources.reservations.index');
    }
}
```

## Notifications

All reservation notifications extend `ReservationNotification`. Remove duplicated constructor, `use Queueable`, and `implements ShouldQueue` from each.

---

### 1. `ReservationCreatedNotification`

Sent at booking time. Explains the confirmation process so the member knows what to expect.

```php
public function toTwilio(object $notifiable): TwilioSmsMessage
{
    return (new TwilioSmsMessage())
        ->content(
            "CMC: Reservation booked for {$this->smsTimeRange()}! "
            . "We'll ask you to confirm 3 days before. {$this->smsUrl()}"
        );
}
```

**Example (106 chars):**
`CMC: Reservation booked for 5/15 7-9 PM! We'll ask you to confirm 3 days before. https://corvmc.org/r/42`

**Bug fix:** `$this->reservation->cost` → `$this->reservation->cost_display` in `toMail()`.

---

### 2. `ReservationConfirmationNotification`

Sent when the confirmation window opens (3 days before). Asks user to confirm and nudges toward online payment.

**Bug fix:** Database channel now included via base `via()`.

```php
public function toTwilio(object $notifiable): TwilioSmsMessage
{
    return (new TwilioSmsMessage())
        ->content(
            "CMC: Please confirm your reservation on {$this->smsTimeRange()}. "
            . "Pay online for instant door code access. {$this->smsUrl()}"
        );
}
```

**Example (131 chars):**
`CMC: Please confirm your reservation on 5/15 7-9 PM. Pay online for instant door code access. https://corvmc.org/r/42`

---

### 3. `ReservationConfirmationReminderNotification`

Follow-up if user hasn't confirmed yet. Warns of auto-cancel.

```php
public function toTwilio(object $notifiable): TwilioSmsMessage
{
    return (new TwilioSmsMessage())
        ->content(
            "CMC: Your reservation {$this->smsTimeRange()} will auto-cancel morning-of if not confirmed. "
            . "{$this->smsUrl()}"
        );
}
```

**Example (103 chars):**
`CMC: Your reservation 5/15 7-9 PM will auto-cancel morning-of if not confirmed. https://corvmc.org/r/42`

---

### 4. `ReservationConfirmedNotification`

Sent after user confirms and payment is initiated. Simple acknowledgement — door code comes in the morning-of reminder.

```php
public function toTwilio(object $notifiable): TwilioSmsMessage
{
    return (new TwilioSmsMessage())
        ->content(
            "CMC: Confirmed! Practice space {$this->smsTimeRange()}. "
            . "{$this->smsUrl()}"
        );
}
```

**Example (68 chars):**
`CMC: Confirmed! Practice space 5/15 7-9 PM. https://corvmc.org/r/42`

---

### 5. `ReservationReminderNotification`

Morning-of reminder (8:00 AM day of reservation). Content varies by payment status.

```php
public function toTwilio(object $notifiable): TwilioSmsMessage
{
    $time = $this->reservation->reserved_at->format('g:i A');
    $body = "CMC: Practice space today at {$time}.";

    if ($this->reservation->lock_code) {
        $body .= " Door code: {$this->reservation->lock_code}.";
    } elseif ($this->isCashPayment()) {
        $body .= " Pay online for self-service access: {$this->smsSwitchUrl()}";
    }

    return (new TwilioSmsMessage())->content($body);
}

private function isCashPayment(): bool
{
    $order = \CorvMC\Finance\Facades\Finance::findActiveOrder($this->reservation);

    if (! $order) {
        return false;
    }

    return $order->transactions()
        ->where('currency', 'cash')
        ->where('type', 'payment')
        ->exists();
}
```

**Example — online user with door code (57 chars):**
`CMC: Practice space today at 7:00 PM. Door code: 482916.`

**Example — cash user (104 chars):**
`CMC: Practice space today at 7:00 PM. Pay online for self-service access: https://corvmc.org/r/42?switch=1`

**Example — free (38 chars):**
`CMC: Practice space today at 7:00 PM.`

Update the email copy from "tomorrow" to "today" to match new timing.

---

### 6. `ReservationAutoCancelledNotification`

Sent when the auto-cancel command runs morning-of for unconfirmed reservations.

```php
public function toTwilio(object $notifiable): TwilioSmsMessage
{
    return (new TwilioSmsMessage())
        ->content(
            "CMC: Your reservation {$this->smsTimeRange()} was auto-cancelled (not confirmed in time). No credits charged."
        );
}
```

**Example (104 chars):**
`CMC: Your reservation 5/15 7-9 PM was auto-cancelled (not confirmed in time). No credits charged.`

---

### 7. `ReservationCancelledNotification`

Sent on manual cancellation by member or staff.

```php
public function toTwilio(object $notifiable): TwilioSmsMessage
{
    return (new TwilioSmsMessage())
        ->content(
            "CMC: Your reservation {$this->smsTimeRange()} has been cancelled. "
            . "Rebook: {$this->smsUrl()}"
        );
}
```

**Example (86 chars):**
`CMC: Your reservation 5/15 7-9 PM has been cancelled. Rebook: https://corvmc.org/r/42`

---

### 8. `FirstReservationMemberNotification` (NEW)

Sent to member at confirmation time when `isFirstForUser()` is true.

```php
public function toTwilio(object $notifiable): TwilioSmsMessage
{
    $time = $this->reservation->reserved_at->format('g:i A');

    return (new TwilioSmsMessage())
        ->content("CMC: Welcome! A staff member will meet you at your first session ({$time}) to show you the space.");
}
```

**Example (97 chars):**
`CMC: Welcome! A staff member will meet you at your first session (7:00 PM) to show you the space.`

---

### 9. `FirstReservationStaffNotification` (NEW)

Sent to staff/admin at confirmation time when `isFirstForUser()` is true. Email + database only, no SMS.

```php
public function via(object $notifiable): array
{
    return ['mail', 'database'];
}
```

---

### 10. `StaffPresenceNoLongerRequiredNotification` (NEW)

Sent to staff when a cash user switches to online payment (and is not a first-timer). Email + database only.

```php
public function via(object $notifiable): array
{
    return ['mail', 'database'];
}
```

---

### `ReservationCreatedTodayNotification`

Admin-only, override `via()` to return `['mail']` only. No SMS.

## Notifications outside this refactor

- `DailyReservationDigestNotification` — admin digest, different constructor (collection of reservations)
- `RehearsalAttendanceRequestedNotification` — rehearsal-specific
- `RehearsalReminderNotification` — rehearsal-specific

## Door code and payment rules

- Lock codes are generated **on payment complete**, not on confirmation directly. For online payments this is immediate; for cash it's when staff collects.
- Cash-paying reservations do NOT receive a lock code at confirmation time. Staff must be present to collect payment and provide access.
- When a user switches from cash to online and payment completes, generate the lock code at that point and send the door code via SMS.
- The door code is communicated to the user in the morning-of reminder (for online payers) or at switch time (if they switch from cash later).
- First-time reservations always require staff regardless of payment method.

## Payment switch flow

Triggered from `PayWithStripeAction` on the reservation view:

1. Cancel the pending cash transaction on the order
2. Create and commit a stripe transaction
3. On payment complete: generate lock code via `UltraloqService::createTemporaryUser()`
4. Send member their door code via SMS
5. If not first-timer: dispatch `StaffPresenceNoLongerRequiredNotification` to staff

Auto-launching: when a cash-paying user clicks the SMS link (`/r/{id}?switch=1`), the reservation view auto-mounts the `PayWithStripeAction` modal.

## Pre-pay nudges

### In the reservation form

De-emphasize the cash option:

- Primary button: "Pay Online — get your door code instantly"
- Secondary/muted: "Pay Cash — requires staff on-site"

Helper text on cash: choosing cash means a staff member must be present, which limits available time slots to staffed hours.

### In SMS

- Confirmation request (#2): "Pay online for instant door code access"
- Morning-of reminder (#5): cash users get "Pay online for self-service access" with `?switch=1` link

## Scheduled command ordering (morning-of)

1. **7:45 AM** — `AutoCancelUnconfirmedReservations`: cancels Scheduled reservations for today that were never confirmed. Dispatches `ReservationAutoCancelledNotification`.
2. **8:00 AM** — `SendReservationReminders`: picks up Confirmed reservations for today only. Dispatches `ReservationReminderNotification`.

This ensures no reminder goes out for a reservation that was just auto-cancelled.

## Detection logic

```php
// On Reservation model
public function isFirstForUser(): bool
{
    return ! static::withoutGlobalScopes()
        ->where('user_id', $this->user_id)
        ->where('id', '!=', $this->id)
        ->whereState('status', Completed::class)
        ->exists();
}
```

## Testing approach

Each `toTwilio()` method needs a test asserting:

1. Message content includes the correct reservation details
2. Message is under 160 characters for representative data
3. SMS channel is present in `via()` when user has phone + opt-in
4. SMS channel is absent when user lacks phone or opt-in
5. SMS channel is absent for notifications without `toTwilio()`

```php
it('sends SMS when user is opted in', function () {
    $user = User::factory()->create([
        'phone' => '+15551234567',
        'settings' => new UserSettingsData(sms_notifications_enabled: true),
    ]);
    $reservation = Reservation::factory()->create();

    $notification = new ReservationConfirmedNotification($reservation);

    expect($notification->via($user))->toContain('twilio');
});

it('does not send SMS when user is not opted in', function () {
    $user = User::factory()->create([
        'phone' => '+15551234567',
        'settings' => new UserSettingsData(sms_notifications_enabled: false),
    ]);
    $reservation = Reservation::factory()->create();

    $notification = new ReservationConfirmedNotification($reservation);

    expect($notification->via($user))->not->toContain('twilio');
});

it('keeps SMS under 160 characters', function () {
    $reservation = Reservation::factory()->create([
        'reserved_at' => now()->addDays(3)->setTime(19, 0),
        'reserved_until' => now()->addDays(3)->setTime(21, 0),
    ]);

    $notification = new ReservationConfirmedNotification($reservation);
    $message = $notification->toTwilio($user);

    expect(strlen($message->content))->toBeLessThanOrEqual(160);
});
```

## Post-reservation followup

### Auto-complete

A scheduled command runs at `reserved_until + 30 min` and transitions all Confirmed reservations whose end time has passed to Completed.

```php
// In routes/console.php or a dedicated command
// Runs every 15 minutes
Reservation::query()
    ->whereState('status', Confirmed::class)
    ->where('reserved_until', '<', now()->subMinutes(30))
    ->each(fn (Reservation $r) => $r->complete());
```

### Staff end-of-session nudge

At `reserved_until + 15 min`, send staff a notification: "Reservation ended — please verify space is empty." This is email + database only, no SMS.

```php
// StaffReservationEndedNotification (NEW)
// Dispatched by the same scheduled sweep or a separate command at :15 past each hour
```

### Attendance tracking (future — pending webhook testing)

The Ultraloq API supports event notification webhooks (`Uhome.Configure.Set` registers a URL). Once registered, we expect unlock events that include the triggering user ID. If confirmed:

- Store `checked_in_at` on the reservation when an unlock event matches the reservation's temp user ID during the reservation window
- Flag `no_show = true` on Completed reservations where `checked_in_at` is null and the temp code was never used

Until the webhook payload is confirmed, no-show detection remains manual (staff checks camera after receiving the end-of-session nudge).

### Ultraloq temporary user cleanup

After auto-completing a reservation, delete the temporary user from the lock via `st.lockUser.delete(id)`. This keeps the lock's user list clean.

---

## Implementation order

1. Add `sms_notifications_enabled` to `UserSettingsData`
2. Install `laravel-notification-channels/twilio`, add env config
3. Create `ReservationNotification` base class (with `smsUrl()`, `smsSwitchUrl()`, `smsTimeRange()` helpers)
4. Refactor existing reservation notifications to extend base (fix ConfirmationNotification bugs inline)
5. Add `toTwilio()` to all member-facing notifications
6. Change `getReminderSendAt()` to return 8:00 AM day-of; update email copy from "tomorrow" to "today"
7. Set scheduled command order: auto-cancel at 7:45 AM, reminders at 8:00 AM
8. Add `isFirstForUser()` method to Reservation model
9. Create `FirstReservationStaffNotification` + `FirstReservationMemberNotification`
10. Create `StaffPresenceNoLongerRequiredNotification`
11. Wire up payment-switch flow in `PayWithStripeAction`: cancel cash txn → commit stripe txn → on complete: generate lock code → SMS door code → notify staff
12. Add short redirect route with authorization gate and `?switch=1` support
13. Auto-mount `PayWithStripeAction` modal when `switch` query param is present on reservation view
14. Update `saveContactInfo` to normalize phone to E.164 and persist SMS opt-in
15. Update reservation form payment step UI to de-emphasize cash option
16. Add `Completed` state transition logic: `complete()` method, `ReservationCompleted` event
17. Add scheduled auto-complete command (every 15 min, sweeps reservations past `reserved_until + 30 min`)
18. Create `StaffReservationEndedNotification` (staff nudge to verify space is clear)
19. Add temp user cleanup: delete Ultraloq user after auto-complete
20. Register Ultraloq webhook and test event payloads (attendance tracking — deferred until payload confirmed)
21. Add tests
