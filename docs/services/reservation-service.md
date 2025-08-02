# ReservationService

The ReservationService encapsulates all business logic for practice space booking, validation, cost calculation, and scheduling management within the Corvallis Music Collective platform.

## Purpose & Responsibilities

The ReservationService serves as the comprehensive business logic layer for the practice space reservation system, handling complex operations including cost calculations for different membership tiers, availability checking, recurring reservation management, and validation against multiple business rules. It abstracts the intricacies of the hybrid pricing model where sustaining members receive benefits while maintaining fair access for all users. The service ensures data integrity, prevents double-booking, and provides consistent cost calculations across all booking interfaces.

This service is essential for maintaining the complex business logic required for a multi-tiered membership system with time-based constraints and financial calculations. It coordinates between the user subscription system to determine membership benefits, validates reservations against operating hours and duration limits, and manages the intricate logic of recurring bookings for sustaining members. The service provides a clean API that shields controllers and interfaces from the complexity of business rule validation while ensuring consistent behavior across web interfaces, APIs, and administrative tools.

## API Reference

### Core Reservation Management

```php
public function createReservation(
    User $user,
    \DateTime $startTime,
    \DateTime $endTime,
    array $options = []
): Reservation
```
Creates a new reservation with full validation and cost calculation.
- **Parameters**: 
  - `$user` - user making the reservation
  - `$startTime` - reservation start time
  - `$endTime` - reservation end time  
  - `$options` - optional parameters (production_id, purpose, skip_validation)
- **Returns**: Created Reservation model
- **Throws**: `InvalidReservationException` for validation failures
- **Business Logic**: 
  - Validates against all business rules (hours, duration, conflicts)
  - Calculates costs based on membership status and available free hours
  - Automatically confirms valid reservations

```php
public function updateReservation(
    Reservation $reservation,
    \DateTime $newStartTime,
    \DateTime $newEndTime,
    array $options = []
): bool
```
Updates existing reservation with revalidation.
- **Parameters**: `$reservation` - existing reservation, `$newStartTime` - new start, `$newEndTime` - new end, `$options` - update options
- **Returns**: `true` on successful update
- **Business Logic**: Revalidates entire reservation, recalculates costs, excludes current reservation from conflict checking

```php
public function cancelReservation(Reservation $reservation): bool
```
Cancels a reservation by setting status to 'cancelled'.
- **Parameters**: `$reservation` - reservation to cancel
- **Returns**: `true` on successful cancellation
- **Business Logic**: Preserves reservation record for audit trail, updates status only

### Validation & Business Rules

```php
public function validateReservation(
    \DateTime $startTime,
    \DateTime $endTime,
    ?User $user = null,
    ?Reservation $excludeReservation = null
): array
```
Comprehensive validation against all business rules.
- **Parameters**: Times to validate, optional user context, optional reservation to exclude
- **Returns**: Array with 'valid' boolean and 'errors' array
- **Business Logic**: Checks future dates, business hours, duration limits, conflicts

```php
public function isTimeSlotAvailable(
    \DateTime $startTime,
    \DateTime $endTime,
    ?Reservation $excludeReservation = null
): bool
```
Quick availability check for time slot.
- **Parameters**: Time range to check, optional reservation to exclude
- **Returns**: `true` if slot is available
- **Business Logic**: Queries for conflicting confirmed reservations, excludes cancelled

```php
public function findConflictingReservations(
    \DateTime $startTime,
    \DateTime $endTime,
    ?Reservation $excludeReservation = null
): Collection
```
Find all reservations that conflict with given time range.
- **Parameters**: Time range to check, optional reservation to exclude
- **Returns**: Collection of conflicting Reservation models
- **Business Logic**: Uses database queries to find overlapping time ranges efficiently

### Cost Calculation

```php
public function calculateCost(
    User $user,
    \DateTime $startTime,
    \DateTime $endTime
): array
```
Calculate total cost with membership benefits applied.
- **Parameters**: User and time range for cost calculation
- **Returns**: Array with cost breakdown:
  - `total_cost` - final cost in dollars
  - `free_hours_used` - hours covered by membership benefits
  - `paid_hours` - hours charged at standard rate
  - `hourly_rate` - rate per hour ($15.00)
- **Business Logic**: 
  - Determines sustaining member status
  - Calculates remaining free hours for current month
  - Applies free hours first, then standard rate for remainder

```php
public function calculateHours(\DateTime $startTime, \DateTime $endTime): float
```
Calculate duration in hours between two times.
- **Parameters**: Start and end times
- **Returns**: Duration as float (e.g., 1.5 for 90 minutes)
- **Business Logic**: Precise calculation using DateTime intervals

### Advanced Features

```php
public function createRecurringReservation(
    User $user,
    \DateTime $startTime,
    \DateTime $endTime,
    int $weeks,
    array $options = []
): array
```
Create weekly recurring reservations (sustaining members only).
- **Parameters**: User, initial time slot, number of weeks, options
- **Returns**: Array with 'created', 'skipped', and 'errors' arrays
- **Throws**: `InvalidReservationException` if user is not sustaining member
- **Business Logic**: 
  - Validates sustaining member status
  - Creates reservations for each week
  - Skips weeks with conflicts rather than failing entire operation
  - Applies individual cost calculations for each reservation

```php
public function getAvailableTimeSlots(
    \DateTime $date,
    int $durationMinutes = 60
): array
```
Get all available time slots for a given date and duration.
- **Parameters**: Target date, desired duration in minutes
- **Returns**: Array of available slots with 'start' and 'end' DateTime objects
- **Business Logic**: 
  - Generates all possible slots within business hours
  - Filters out conflicting reservations
  - Returns only slots that fit the requested duration

```php
public function getUserStats(User $user): array
```
Get comprehensive reservation statistics for user.
- **Parameters**: User to analyze
- **Returns**: Statistics array:
  - `total_reservations` - all-time reservation count
  - `total_hours` - cumulative hours booked
  - `total_cost` - lifetime spending on reservations
  - `free_hours_used_this_month` - current month's benefit usage
  - `upcoming_reservations` - count of future reservations
- **Business Logic**: Aggregates data across user's entire reservation history

## Usage Examples

### Basic Reservation Creation
```php
$service = new ReservationService();
$user = User::find(1);

try {
    $reservation = $service->createReservation(
        $user,
        now()->addDay()->setTime(14, 0),     // Tomorrow 2 PM
        now()->addDay()->setTime(16, 0),     // Until 4 PM
        ['purpose' => 'Band practice']
    );
    
    echo "Reserved! Cost: $" . $reservation->total_cost;
} catch (InvalidReservationException $e) {
    echo "Booking failed: " . $e->getMessage();
}
```

### Validation Before Booking
```php
$validation = $service->validateReservation(
    now()->addWeek()->setTime(18, 0),
    now()->addWeek()->setTime(20, 0),
    $user
);

if ($validation['valid']) {
    // Proceed with booking
    $cost = $service->calculateCost($user, $startTime, $endTime);
    echo "This reservation will cost: $" . $cost['total_cost'];
} else {
    foreach ($validation['errors'] as $error) {
        echo "Error: " . $error;
    }
}
```

### Recurring Reservations for Sustaining Members
```php
$sustainingMember = User::find(2);

if ($sustainingMember->isSustainingMember()) {
    $result = $service->createRecurringReservation(
        $sustainingMember,
        now()->next('Wednesday')->setTime(19, 0),  // Weekly Wednesday 7 PM
        now()->next('Wednesday')->setTime(21, 0),  // Until 9 PM
        12  // For 12 weeks
    );
    
    echo "Created: " . count($result['created']) . " reservations";
    echo "Skipped: " . count($result['skipped']) . " due to conflicts";
}
```

### Availability and Scheduling
```php
// Get available 2-hour slots for tomorrow
$availableSlots = $service->getAvailableTimeSlots(
    now()->addDay(),
    120  // 2 hours in minutes
);

echo "Available slots tomorrow:";
foreach ($availableSlots as $slot) {
    echo $slot['start']->format('g:i A') . " - " . $slot['end']->format('g:i A');
}
```

## Integration Points

- **User Model**: Membership status checking and free hour calculations
- **UserSubscriptionService**: Sustaining member status validation
- **Reservation Model**: Direct model manipulation and status management
- **Transaction System**: Cost calculation and billing integration
- **Production System**: Optional linking of reservations to events
- **Calendar Interfaces**: Availability data for booking interfaces
- **Administrative Dashboard**: Statistics and usage reporting
- **Email System**: Confirmation and reminder notifications

## Business Rules Enforced

- **Operating Hours**: 8 AM to 10 PM daily
- **Duration Limits**: 1-8 hours per reservation
- **Future Booking Only**: No past-date reservations
- **Conflict Prevention**: No overlapping reservations
- **Membership Benefits**: 4 free hours/month for sustaining members
- **Recurring Privileges**: Only sustaining members can create recurring bookings
- **Cost Structure**: $15/hour standard rate with member discounts
- **Cancellation Policy**: Reservations cancelled, not deleted
- **Validation Hierarchy**: Time → Duration → Availability → Business Rules