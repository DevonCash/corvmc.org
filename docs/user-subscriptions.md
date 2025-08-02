# User Subscription System

The User Subscription System manages membership levels and benefits for the Corvallis Music Collective, tracking sustaining member status, processing recurring donations, and providing enhanced features to supporters.

## Business Logic & Workflow

The subscription system operates on a flexible membership model where users can become "sustaining members" through either role assignment or recurring financial contributions. Sustaining members receive enhanced privileges including 4 free practice space hours per month, the ability to create recurring reservations, and priority booking for special events. The system automatically tracks membership status based on recent transactions, with users qualifying for benefits if they have a recurring transaction over $10 within the last month.

The workflow centers around automated membership management and benefit tracking. When users make qualifying recurring donations through the integrated payment system, the subscription service processes these transactions and automatically grants or maintains sustaining member status. The system continuously monitors transaction history to ensure membership benefits remain current, automatically revoking benefits when subscriptions lapse or payments fail. Monthly free hour allocations reset automatically, ensuring fair distribution of benefits across all sustaining members.

Administrative functions provide comprehensive oversight of the membership program, including subscription statistics, revenue tracking, and member lifecycle management. The system generates insights into subscription health, identifies members whose subscriptions are approaching expiration, and provides tools for manual membership management when needed. This dual approach of automated processing with administrative oversight ensures the sustainability of the collective's membership program while maintaining transparency and member satisfaction.

## API Reference

### User Model Extensions

#### Membership Status Methods
```php
public function isSustainingMember(): bool
// Check if user is a sustaining member (role or recent transaction)

public function getUsedFreeHoursThisMonth(): float  
// Get free hours used in current month

public function getRemainingFreeHours(): float
// Get remaining free hours for month (max 4)

// Relationships
public function transactions()
// Get user's transaction history (linked by email)
```

### UserSubscriptionService API

#### Membership Status Management
```php
public function isSustainingMember(User $user): bool
// Comprehensive check for sustaining member status
// Checks both role assignment and recent qualifying transactions

public function getSubscriptionStatus(User $user): array
// Get detailed subscription information
// Returns: [
//   'is_sustaining_member' => bool,
//   'method' => 'role'|'transaction'|null,
//   'expires_at' => ?datetime,
//   'last_transaction' => ?Transaction,
//   'free_hours_remaining' => float
// ]

public function grantSustainingMemberStatus(User $user): bool
// Manually grant sustaining member role

public function revokeSustainingMemberStatus(User $user): bool
// Remove sustaining member role (if assigned via role, not transaction)
```

#### Transaction Processing
```php
public function processTransaction(Transaction $transaction): bool
// Process transaction and update membership status if qualifying
// Automatically grants sustaining member role for qualifying transactions

public function getSustainingMembers(): Collection
// Get all current sustaining members (role + transaction based)

public function getSustainingMembersByRole(): Collection
// Get sustaining members with explicit role assignment

public function getSustainingMembersByTransaction(): Collection
// Get sustaining members based on recent transactions
```

#### Analytics & Statistics
```php
public function getSubscriptionStatistics(): array
// Get comprehensive subscription metrics
// Returns: [
//   'total_sustaining_members' => int,
//   'role_based_members' => int, 
//   'transaction_based_members' => int,
//   'monthly_recurring_revenue' => float,
//   'average_transaction_amount' => float,
//   'total_transactions_this_month' => int
// ]

public function getExpiringSubscriptions(int $days = 7): Collection
// Get subscriptions expiring within specified days

public function getFreeHoursUsage(User $user, ?datetime $month = null): array
// Get detailed free hours usage for user/month
// Returns: [
//   'total_hours' => float,
//   'reservations' => Collection,
//   'remaining_hours' => float
// ]
```

### Transaction Model Integration

#### Properties
```php
public string $email;                  // User email (relationship key)
public string $type;                   // 'one-time', 'recurring' 
public float $amount;                  // Transaction amount
public string $status;                 // 'completed', 'pending', 'failed'
public ?string $stripe_payment_id;     // External payment reference
public datetime $created_at;           // Transaction date

// Relationships
public User $user;                     // User by email lookup
```

#### Qualification Criteria
- Transaction type must be 'recurring'
- Amount must be greater than $10.00
- Transaction must be within the last 30 days
- Status must be 'completed'

## Usage Examples

### Checking Membership Status
```php
$subscriptionService = new UserSubscriptionService();
$user = User::find(1);

// Check if user is sustaining member
if ($subscriptionService->isSustainingMember($user)) {
    $status = $subscriptionService->getSubscriptionStatus($user);
    
    echo "Sustaining member via: " . $status['method'];
    echo "Free hours remaining: " . $status['free_hours_remaining'];
    
    if ($status['expires_at']) {
        echo "Expires: " . $status['expires_at']->format('M j, Y');
    }
}
```

### Processing Donations/Subscriptions
```php
// Transaction comes from payment processor (Stripe, etc.)
$transaction = Transaction::create([
    'email' => 'member@example.com',
    'type' => 'recurring',
    'amount' => 25.00,
    'status' => 'completed',
    'stripe_payment_id' => 'pi_abc123'
]);

// Process transaction - automatically grants sustaining member status
$processed = $subscriptionService->processTransaction($transaction);

if ($processed) {
    $user = User::where('email', $transaction->email)->first();
    echo $user->name . " is now a sustaining member!";
}
```

### Administrative Management
```php
// Get all sustaining members
$sustainingMembers = $subscriptionService->getSustainingMembers();
echo "Total sustaining members: " . $sustainingMembers->count();

// Get subscription statistics for dashboard
$stats = $subscriptionService->getSubscriptionStatistics();
echo "Monthly recurring revenue: $" . $stats['monthly_recurring_revenue'];
echo "Average donation: $" . $stats['average_transaction_amount'];

// Find expiring subscriptions  
$expiring = $subscriptionService->getExpiringSubscriptions(7);
foreach ($expiring as $user) {
    echo $user->name . "'s subscription expires soon";
    // Send renewal reminder email
}
```

### Manual Membership Management
```php
// Manually grant sustaining member status (for volunteers, etc.)
$volunteer = User::find(5);
$subscriptionService->grantSustainingMemberStatus($volunteer);

// Revoke sustaining member status (only works for role-based, not transaction-based)
$formerMember = User::find(10);
if ($subscriptionService->revokeSustainingMemberStatus($formerMember)) {
    echo "Sustaining member status revoked";
} else {
    echo "Cannot revoke - member status is transaction-based";
}
```

### Free Hours Tracking
```php
$sustainingMember = User::find(3);

// Get current month's usage
$usage = $subscriptionService->getFreeHoursUsage($sustainingMember);
echo "Used: " . $usage['total_hours'] . " hours";
echo "Remaining: " . $usage['remaining_hours'] . " hours";

// List reservations that used free hours
foreach ($usage['reservations'] as $reservation) {
    echo "Used " . $reservation->free_hours_used . " free hours on " 
         . $reservation->reserved_at->format('M j');
}
```

### Integration with Reservation System
```php
// When creating reservations, the system automatically applies benefits
$reservationService = new ReservationService();

$reservation = $reservationService->createReservation(
    $sustainingMember,
    now()->addDay()->setTime(19, 0),
    now()->addDay()->setTime(21, 0) // 2 hours
);

// For sustaining members with remaining free hours:
// - free_hours_used = 2.0
// - paid_hours = 0.0  
// - total_cost = $0.00

// For regular members:
// - free_hours_used = 0.0
// - paid_hours = 2.0
// - total_cost = $30.00
```

## Integration Points

- **User Authentication**: Membership status affects user capabilities throughout app
- **Reservation System**: Free hours and recurring reservation privileges
- **Payment Processing**: Transaction monitoring for automatic membership renewal
- **Email System**: Renewal reminders and membership confirmations
- **Administrative Dashboard**: Membership metrics and management tools
- **Role Management**: Integration with Spatie Permission system
- **Billing System**: Revenue tracking and financial reporting

## Business Rules & Benefits

### Sustaining Member Qualification
- **Role Assignment**: Manual assignment by administrators
- **Transaction-Based**: Recurring donation > $10/month within last 30 days
- **Automatic Processing**: Qualifying transactions automatically grant status
- **Renewal**: Transaction-based memberships auto-renew with continued payments

### Sustaining Member Benefits  
- **Free Practice Hours**: 4 hours per month, resets monthly
- **Recurring Reservations**: Weekly recurring booking privileges
- **Priority Access**: Enhanced booking capabilities
- **Community Recognition**: Special status in member directory

### Administrative Controls
- **Manual Override**: Administrators can grant/revoke role-based memberships
- **Transaction Monitoring**: Automatic processing of qualifying payments
- **Expiration Tracking**: 7-day advance warning for expiring subscriptions
- **Usage Analytics**: Detailed insights into membership program performance
- **Revenue Reporting**: Monthly recurring revenue and donation tracking