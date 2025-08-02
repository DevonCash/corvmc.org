# UserSubscriptionService  

The UserSubscriptionService manages membership levels, subscription processing, and benefit tracking for the Corvallis Music Collective's sustaining member program.

## Purpose & Responsibilities

The UserSubscriptionService encapsulates the complex business logic required to manage a hybrid membership system that supports both role-based and transaction-based sustaining memberships. It provides automated processing of recurring donations, tracks membership benefits like free practice hours, and maintains comprehensive analytics for the collective's subscription program. The service ensures consistent membership status determination across the platform while providing administrative tools for manual membership management and subscription oversight.

This service is critical for maintaining the financial sustainability of the collective through its membership program. It automates the processing of recurring donations from payment systems like Stripe, automatically granting membership benefits to qualifying donors while providing manual override capabilities for special cases like volunteers or staff. The service coordinates with the reservation system to track benefit usage, generates detailed analytics for program management, and provides proactive monitoring of subscription health to prevent membership lapses.

## API Reference

### Membership Status Management

```php
public function isSustainingMember(User $user): bool
```
Comprehensive check for sustaining member status.
- **Parameters**: `$user` - user to check
- **Returns**: `true` if user qualifies as sustaining member
- **Business Logic**: 
  - Checks for explicit 'sustaining member' role assignment
  - Validates recent qualifying transactions (recurring, >$10, last 30 days)
  - Returns true if either condition is met

```php
public function getSubscriptionStatus(User $user): array
```
Get detailed subscription information for user.
- **Parameters**: `$user` - user to analyze
- **Returns**: Comprehensive status array:
  - `is_sustaining_member` - boolean status
  - `method` - 'role', 'transaction', or null
  - `expires_at` - expiration date for transaction-based memberships
  - `last_transaction` - most recent qualifying transaction
  - `free_hours_remaining` - current month's remaining free hours
- **Business Logic**: Determines membership source and calculates relevant dates/benefits

```php
public function grantSustainingMemberStatus(User $user): bool
```
Manually assign sustaining member role.
- **Parameters**: `$user` - user to grant status
- **Returns**: `true` on successful assignment
- **Business Logic**: Uses Spatie Permission system to assign role, useful for volunteers/staff

```php
public function revokeSustainingMemberStatus(User $user): bool
```
Remove sustaining member role (role-based only).
- **Parameters**: `$user` - user to revoke status
- **Returns**: `true` if role was removed, `false` if transaction-based
- **Business Logic**: Only removes explicit role assignments, cannot revoke transaction-based status

### Transaction Processing

```php
public function processTransaction(Transaction $transaction): bool
```
Process transaction and update membership status if qualifying.
- **Parameters**: `$transaction` - transaction to process
- **Returns**: `true` if transaction qualified user for sustaining membership
- **Business Logic**: 
  - Validates transaction meets qualification criteria
  - Automatically assigns sustaining member role for qualifying transactions
  - Links transaction to user account via email matching

```php
public function getSustainingMembers(): Collection
```
Get all current sustaining members regardless of method.
- **Returns**: Collection of User models with sustaining member status
- **Business Logic**: Combines role-based and transaction-based members into single collection

```php
public function getSustainingMembersByRole(): Collection
```
Get sustaining members with explicit role assignment.
- **Returns**: Collection of users with 'sustaining member' role
- **Business Logic**: Queries users with specific role, useful for manual membership tracking

```php
public function getSustainingMembersByTransaction(): Collection
```
Get sustaining members based on recent qualifying transactions.
- **Returns**: Collection of users with qualifying transactions in last 30 days
- **Business Logic**: Joins users and transactions to find qualifying donation-based memberships

### Analytics & Reporting

```php
public function getSubscriptionStatistics(): array
```
Generate comprehensive subscription program metrics.
- **Returns**: Statistics array:
  - `total_sustaining_members` - combined count of all sustaining members
  - `role_based_members` - count of role-assigned members
  - `transaction_based_members` - count of donation-based members
  - `monthly_recurring_revenue` - total recurring revenue this month
  - `average_transaction_amount` - mean transaction value
  - `total_transactions_this_month` - transaction volume
- **Business Logic**: Aggregates data across membership types and transaction history

```php
public function getExpiringSubscriptions(int $days = 7): Collection
```
Find subscriptions expiring within specified timeframe.
- **Parameters**: `$days` - lookahead period (default 7 days)
- **Returns**: Collection of users whose transaction-based memberships expire soon
- **Business Logic**: Identifies users whose last qualifying transaction is approaching 30-day expiration

```php
public function getFreeHoursUsage(User $user, ?\DateTime $month = null): array
```
Get detailed free hours usage for specific user and month.
- **Parameters**: `$user` - user to analyze, `$month` - specific month (defaults to current)
- **Returns**: Usage breakdown:
  - `total_hours` - free hours used in specified month
  - `reservations` - collection of reservations using free hours
  - `remaining_hours` - unused free hours for month
- **Business Logic**: Calculates usage from reservation records, respects 4-hour monthly limit

## Usage Examples

### Checking and Managing Membership Status
```php
$service = new UserSubscriptionService();
$user = User::find(1);

// Check membership status
if ($service->isSustainingMember($user)) {
    $status = $service->getSubscriptionStatus($user);
    
    echo "Member via: " . $status['method'];
    echo "Free hours remaining: " . $status['free_hours_remaining'];
    
    if ($status['method'] === 'transaction' && $status['expires_at']) {
        echo "Expires: " . $status['expires_at']->format('M j, Y');
    }
}

// Manual membership management
$volunteer = User::find(5);
$service->grantSustainingMemberStatus($volunteer);
echo $volunteer->name . " is now a sustaining member";
```

### Processing Subscription Payments
```php
// Webhook from payment processor (Stripe, etc.)
$transactionData = [
    'email' => 'donor@example.com',
    'type' => 'recurring',
    'amount' => 25.00,
    'status' => 'completed',
    'stripe_payment_id' => 'pi_1234567890'
];

$transaction = Transaction::create($transactionData);

// Process transaction - automatically grants benefits
if ($service->processTransaction($transaction)) {
    $user = User::where('email', $transaction->email)->first();
    
    // Send confirmation email
    Mail::to($user)->send(new SustainingMemberWelcome($user));
    
    echo $user->name . " is now a sustaining member via donation!";
}
```

### Administrative Reporting and Management
```php
// Generate dashboard statistics
$stats = $service->getSubscriptionStatistics();

echo "Program Overview:";
echo "Total sustaining members: " . $stats['total_sustaining_members'];
echo "Monthly recurring revenue: $" . number_format($stats['monthly_recurring_revenue'], 2);
echo "Average donation: $" . number_format($stats['average_transaction_amount'], 2);

// Member breakdown
echo "Role-based members: " . $stats['role_based_members'];
echo "Transaction-based members: " . $stats['transaction_based_members'];

// Find members needing renewal reminders
$expiring = $service->getExpiringSubscriptions(7);
foreach ($expiring as $user) {
    echo $user->name . "'s subscription expires soon";
    // Queue renewal reminder email
    RenewalReminder::dispatch($user);
}
```

### Free Hours Usage Tracking
```php
$sustainingMember = User::find(10);

// Current month usage
$usage = $service->getFreeHoursUsage($sustainingMember);
echo "Used: " . $usage['total_hours'] . " of 4 free hours";
echo "Remaining: " . $usage['remaining_hours'] . " hours";

// Historical usage (specific month)
$lastMonth = now()->subMonth();
$historicalUsage = $service->getFreeHoursUsage($sustainingMember, $lastMonth);
echo "Last month usage: " . $historicalUsage['total_hours'] . " hours";

// List reservations that used free hours
foreach ($usage['reservations'] as $reservation) {
    echo "• " . $reservation->free_hours_used . " hours on " 
         . $reservation->reserved_at->format('M j, g:i A');
}
```

### Membership Analytics and Insights
```php
// Get all sustaining members for analysis
$allMembers = $service->getSustainingMembers();
$roleBasedMembers = $service->getSustainingMembersByRole();
$transactionBasedMembers = $service->getSustainingMembersByTransaction();

echo "Membership Breakdown:";
echo "Total: " . $allMembers->count();
echo "Via Role Assignment: " . $roleBasedMembers->count();
echo "Via Donations: " . $transactionBasedMembers->count();

// Analyze subscription health
$expiringIn30Days = $service->getExpiringSubscriptions(30);
$retentionRate = ($allMembers->count() - $expiringIn30Days->count()) / $allMembers->count() * 100;

echo "30-day retention rate: " . number_format($retentionRate, 1) . "%";
```

## Integration Points

- **User Model**: Direct integration for membership status checking and free hour calculations
- **Transaction Model**: Processing recurring donations and qualification validation
- **Spatie Permission System**: Role-based membership management
- **ReservationService**: Free hour benefit application and usage tracking
- **Payment Processing**: Webhook integration for automated membership processing
- **Email System**: Membership confirmations and renewal reminders
- **Administrative Dashboard**: Statistics and member management interfaces
- **Calendar System**: Benefit application for recurring reservations

## Business Rules & Qualification Criteria

### Sustaining Member Qualification
- **Role-Based**: Manual assignment of 'sustaining member' role by administrators
- **Transaction-Based**: Automatic qualification through recurring donations
  - Transaction type must be 'recurring'
  - Amount must exceed $10.00
  - Transaction must be within last 30 days
  - Status must be 'completed'

### Benefit Structure
- **Free Practice Hours**: 4 hours per month, resets on calendar month boundaries
- **Recurring Reservations**: Exclusive access to weekly recurring booking system
- **Membership Duration**: 
  - Role-based: Permanent until manually revoked
  - Transaction-based: 30 days from last qualifying transaction

### Administrative Controls
- **Manual Override**: Administrators can grant/revoke role-based memberships
- **Automatic Processing**: Qualifying transactions automatically grant status
- **Expiration Monitoring**: System tracks and reports approaching expirations
- **Usage Analytics**: Detailed tracking of benefit utilization for program optimization