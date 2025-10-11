# Time Handling in Corvallis Music Collective

## Current Configuration

- **App Timezone**: `America/Los_Angeles` (PST/PDT)
- **Database**: PostgreSQL (stores timestamps in UTC internally)
- **PHP Timezone**: Set via `config('app.timezone')`

## Historical Context

A timezone migration was run (`2025_10_10_185520_fix_reservation_timezones.php`) that added 8 hours to all existing timestamps. This was because:
- The app was initially using UTC
- Users were entering times thinking they were PST/PDT
- When the app timezone changed to `America/Los_Angeles`, all existing times displayed 8 hours off
- The migration corrected this by shifting all timestamps forward

## Current Issues and Inconsistencies

### 1. **Unnecessary `Carbon::parse()` Calls**

**Problem**: Re-parsing already-parsed Carbon instances can cause timezone shifts.

**Locations**:
- `app/Filament/Resources/Reservations/Pages/CreateReservation.php:70`
  ```php
  $reservationDate = Carbon::parse($record->reserved_at); // WRONG - already Carbon
  ```
- `app/Filament/Resources/Reservations/Schemas/ReservationForm.php:58`
  ```php
  $resDate = Carbon::parse($reservationDate); // Already a date string
  ```
- `app/Filament/Resources/Reservations/Schemas/ReservationForm.php:172`
  ```php
  return GetAvailableTimeSlotsForDate::run(Carbon::parse($date)); // OK - date is a string
  ```
- `app/Filament/Resources/Reservations/Schemas/ReservationForm.php:195`
  ```php
  return GetValidEndTimesForDate::run(Carbon::parse($date), $startTime); // OK
  ```

**Recently Fixed** (in today's commits):
- ✅ `EditReservation.php` - Now checks `instanceof Carbon` before parsing
- ✅ `EditSpaceUsage.php` - Now checks `instanceof Carbon` before parsing

### 2. **Missing Timezone Specifications**

**Problem**: When creating Carbon instances from strings without explicit timezone, it may default to UTC.

**Location**:
- `app/Filament/Resources/Reservations/Schemas/ReservationForm.php:285-289`
  ```php
  if ($date && $startTime) {
      $set('reserved_at', $date . ' ' . $startTime); // No timezone!
  }
  ```

**Recently Fixed**:
- ✅ `EditReservation.php:44` - Now uses `config('app.timezone')`
- ✅ `EditSpaceUsage.php:44` - Now uses `config('app.timezone')`

**Still Needs Fixing**:
- ❌ `ReservationForm.php` - Needs to specify timezone when combining date/time

### 3. **Model Casts**

**Correct**: The `Reservation` model properly casts datetime fields:
```php
protected function casts(): array
{
    return [
        'reserved_at' => 'datetime',
        'reserved_until' => 'datetime',
        'paid_at' => 'datetime',
        // ...
    ];
}
```

This means:
- Database stores as UTC (PostgreSQL convention)
- Laravel automatically converts to app timezone (`America/Los_Angeles`) when reading
- Converts back to UTC when saving

## Best Practices Going Forward

### ✅ DO

1. **Trust Model Casts**
   ```php
   // $record->reserved_at is already a Carbon instance in app timezone
   $time = $record->reserved_at->format('H:i');
   ```

2. **Check Instance Type**
   ```php
   $reservedAt = $data['reserved_at'] instanceof Carbon
       ? $data['reserved_at']
       : Carbon::parse($data['reserved_at']);
   ```

3. **Specify Timezone When Creating from Strings**
   ```php
   $startTime = Carbon::parse($date . ' ' . $time, config('app.timezone'));
   ```

4. **Use `now()` and `today()` Helpers**
   ```php
   $now = now(); // Automatically uses app timezone
   $today = today(); // Automatically uses app timezone
   ```

### ❌ DON'T

1. **Don't Re-parse Carbon Instances**
   ```php
   // BAD
   $time = Carbon::parse($record->reserved_at);

   // GOOD
   $time = $record->reserved_at;
   ```

2. **Don't Create Datetimes Without Timezone**
   ```php
   // BAD
   Carbon::parse('2024-10-11 14:00'); // Uses UTC!

   // GOOD
   Carbon::parse('2024-10-11 14:00', 'America/Los_Angeles');
   ```

3. **Don't Use `toDateString()` Then Re-parse**
   ```php
   // BAD
   $date = $record->reserved_at->toDateString(); // "2024-10-11"
   $parsed = Carbon::parse($date); // Lost time and timezone info!

   // GOOD
   $date = $record->reserved_at->copy(); // Keep all info
   ```

## Components Affected by Time Handling

### Forms
- `ReservationForm.php` - Creates new reservations (NEEDS FIX)
- `ReservationEditForm.php` - Edits existing reservations (FIXED)
- `ProductionForm.php` - Event scheduling

### Actions
- `CreateReservation.php` - Accepts Carbon with timezone
- `UpdateReservation.php` - Accepts Carbon with timezone
- `GetAvailableTimeSlotsForDate.php` - Works with date strings
- `GetValidEndTimesForDate.php` - Works with date strings
- `CalculateReservationCost.php` - Works with Carbon instances

### Pages
- `CreateReservation.php` - NEEDS FIX (line 70)
- `EditReservation.php` - FIXED
- `EditSpaceUsage.php` - FIXED

### Models
- `Reservation` - ✅ Proper datetime casts
- `RehearsalReservation` - ✅ Inherits from Reservation
- `ProductionReservation` - ✅ Inherits from Reservation
- `Production` - ✅ Has datetime casts for start_time/end_time

## Testing Timezone Handling

When testing, verify:

1. **Create a reservation for 2:00 PM PST**
   - Displays as "2:00 PM" in views
   - Shows "2:00 PM" when editing
   - Saves to database as "22:00 UTC" (during PST)

2. **Edit a reservation**
   - Time shows correctly in edit form
   - Saving doesn't shift the time
   - View page shows same time as before edit

3. **Daylight Saving Time**
   - PST (winter): UTC -8 hours
   - PDT (summer): UTC -7 hours
   - Carbon handles this automatically with `America/Los_Angeles`

## ✅ Resolved Issues (Fixed)

All identified issues have been resolved:

### ✅ Fixed: ReservationForm.php - updateDateTimes()
Now explicitly specifies timezone when combining date and time strings.

### ✅ Fixed: CreateReservation.php - shouldRedirectToCheckout()
Removed unnecessary Carbon::parse() on already-Carbon model attribute.

### ✅ Fixed: ReservationForm.php - shouldShowCheckout()
Now specifies timezone when parsing date string.

### ✅ Fixed: CreateProduction.php - conflict detection
Handles both Carbon instances and strings with proper timezone specification.

### ✅ Fixed: ListReservations.php - quick create action
Uses Carbon instances directly from form without re-parsing.

## Summary

The application's time handling **mostly works correctly** due to:
- Proper model casts converting to/from UTC
- App timezone set to `America/Los_Angeles`
- Recent fixes to edit pages

However, there are **2-3 remaining issues** where:
- Carbon instances are re-parsed unnecessarily
- Timezone isn't specified when creating from strings

These should be fixed to ensure complete consistency, especially as the app continues to grow.
