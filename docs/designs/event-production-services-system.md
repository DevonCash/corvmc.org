# Event Production Services System Design

## Overview

A comprehensive event production services management system for booking coordination and live sound support. Formalizes CMC's production services with structured pricing, client management, equipment tracking, staffing coordination, and project workflow. Focuses on community-scale events (up to 200-300 capacity) that traditional production companies overlook, with tiered pricing supporting non-profits and community organizations.

## Goals

- **Formalize Services**: Replace ad-hoc system with structured offerings
- **Client Management**: Track inquiries, quotes, bookings, and history
- **Equipment Tracking**: Manage inventory, availability, maintenance
- **Staff Scheduling**: Coordinate engineers, crew, booking coordinators
- **Project Workflow**: Quote → Contract → Planning → Execution → Invoice
- **Tiered Pricing**: Standard rates with non-profit/sponsor discounts
- **Revenue Generation**: Sustainable pricing while serving community
- **Quality Control**: Consistent service delivery and documentation

## Core Features

### 1. Service Offerings

#### Booking Coordination
- Artist sourcing and negotiation
- Contract management
- Payment coordination
- Timeline development
- Day-of coordination

#### Live Sound Engineering
- PA system provision
- Sound engineering services
- Setup and teardown
- Technical support
- Equipment transport

#### Event Consultation
- Planning assistance
- Timeline development
- Vendor coordination
- Budget development
- Logistics planning

#### Equipment Rental (Standalone)
- PA systems
- Microphones
- Lighting (future)
- Staging (future)
- Self-operated or with crew

### 2. Service Packages

**Basic Sound Package** - $500 base
- 150-person capacity PA
- 1 sound engineer (4 hours)
- Basic mic package
- Setup/teardown
- Local transport

**Standard Sound Package** - $800 base
- 250-person capacity PA
- 1 sound engineer (6 hours)
- Full mic package
- Setup/teardown
- Lighting consultation

**Premium Package** - $1,500 base
- 300-person capacity PA
- Lead engineer + assistant
- Full production (8 hours)
- Advanced mic package
- Basic lighting
- Stage management

**Booking Coordination** - $250-$500
- Artist sourcing
- Contract negotiation
- Payment coordination
- Timeline development

**Event Consultation** - $100/hour
- Planning sessions
- Vendor recommendations
- Budget development
- Timeline creation

### 3. Pricing Tiers

**Standard Rate** - Full price
- Commercial events
- Private parties
- Corporate events
- Weddings

**Non-Profit Rate** - 50% discount
- 501(c)(3) organizations
- Community festivals
- Fundraisers
- Educational events

**Crescendo Sponsor Rate** - 50% discount
- Active Crescendo sponsors
- Partnership benefits
- Promotional opportunities

**Educational Rate** - 30% discount
- Schools and universities
- Student organizations
- Educational programming

**Multi-Event Rate** - 10-20% discount
- 3+ events booked
- Seasonal contracts
- Ongoing partnerships

## Database Schema

### Tables

#### `production_services`
```
id - bigint primary key
name - string
slug - string unique
description - text
service_category - enum (sound, booking, consultation, equipment_rental, package)
base_price - integer (cents)
hourly_rate - integer nullable (cents)
minimum_hours - integer nullable
included_equipment - json (equipment IDs or categories)
included_staff - json (role types and quantities)
capacity_range - string nullable (e.g., "150-250 people")
is_active - boolean (default true)
settings - json
created_at, updated_at
```

#### `production_clients`
```
id - bigint primary key
organization_name - string
client_type - enum (commercial, nonprofit, educational, government, individual)
tax_id - string nullable (EIN for nonprofits)
primary_contact_name - string
primary_contact_email - string
primary_contact_phone - string
billing_address - text nullable
is_crescendo_sponsor - boolean (default false)
discount_tier - enum (standard, nonprofit, sponsor, educational) nullable
notes - text nullable
created_at, updated_at, deleted_at
```

#### `production_inquiries`
```
id - bigint primary key
production_client_id - foreign key nullable (if returning client)
contact_name - string
contact_email - string
contact_phone - string nullable
organization_name - string nullable
event_name - string
event_type - enum (concert, festival, fundraiser, corporate, wedding, community, other)
event_date - date nullable
event_time - time nullable
venue_name - string nullable
venue_address - text nullable
expected_capacity - integer nullable
services_requested - json (service IDs or descriptions)
budget_range - string nullable
additional_details - text nullable
status - enum (new, contacted, quoted, converted, declined, archived)
contacted_at - timestamp nullable
contacted_by - foreign key to users nullable
converted_to_project_id - foreign key nullable
created_at, updated_at
```

#### `production_projects`
```
id - bigint primary key
production_client_id - foreign key
project_number - string unique (e.g., "PROD-2025-001")
event_name - string
event_type - enum (concert, festival, fundraiser, corporate, wedding, community, other)
event_date - date
event_start_time - time
event_end_time - time
load_in_time - time nullable
load_out_time - time nullable
venue_name - string
venue_address - text
venue_contact_name - string nullable
venue_contact_phone - string nullable
expected_capacity - integer nullable
status - enum (quoted, contracted, planning, confirmed, in_progress, completed, cancelled, invoiced, paid)
quoted_at - timestamp nullable
contracted_at - timestamp nullable
completed_at - timestamp nullable
cancelled_at - timestamp nullable
cancellation_reason - text nullable
project_manager_id - foreign key to users
notes - text nullable
created_at, updated_at, deleted_at
```

#### `production_project_services`
```
id - bigint primary key
production_project_id - foreign key
production_service_id - foreign key nullable (if standard service)
custom_service_name - string nullable (if custom)
description - text nullable
quantity - integer (default 1)
hours - decimal nullable
base_price - integer (cents)
discount_percentage - integer (default 0)
discount_amount - integer (cents)
subtotal - integer (cents)
notes - text nullable
created_at, updated_at
```

#### `production_quotes`
```
id - bigint primary key
production_project_id - foreign key
quote_number - string unique
version - integer (for revisions)
valid_until - date
subtotal - integer (cents)
discount_percentage - integer (default 0)
discount_amount - integer (cents)
tax_amount - integer (cents)
total - integer (cents)
terms - text (payment terms, cancellation policy)
internal_notes - text nullable
status - enum (draft, sent, accepted, declined, expired, revised)
sent_at - timestamp nullable
accepted_at - timestamp nullable
declined_at - timestamp nullable
decline_reason - text nullable
created_by - foreign key to users
created_at, updated_at
```

#### `production_contracts`
```
id - bigint primary key
production_project_id - foreign key
contract_number - string unique
contract_text - longtext (full contract content)
deposit_percentage - integer (default 50)
deposit_amount - integer (cents)
balance_amount - integer (cents)
payment_due_date - date
cancellation_policy - text
signed_by_client_name - string nullable
signed_by_client_at - timestamp nullable
signed_by_cmc_name - string nullable
signed_by_cmc_at - timestamp nullable
contract_file_url - string nullable (signed PDF)
status - enum (draft, sent, signed, cancelled)
created_at, updated_at
```

#### `production_staff_assignments`
```
id - bigint primary key
production_project_id - foreign key
user_id - foreign key to users
role - enum (lead_engineer, assistant_engineer, booking_coordinator, crew, project_manager, stage_manager)
scheduled_start - timestamp
scheduled_end - timestamp
hourly_rate - integer nullable (cents, for compensation)
status - enum (scheduled, confirmed, completed, cancelled)
actual_start - timestamp nullable
actual_end - timestamp nullable
hours_worked - decimal nullable
notes - text nullable
created_at, updated_at
```

#### `production_equipment_assignments`
```
id - bigint primary key
production_project_id - foreign key
equipment_id - foreign key to equipment
quantity - integer (default 1)
status - enum (reserved, loaded, deployed, returned, damaged)
checkout_time - timestamp nullable
return_time - timestamp nullable
checked_out_by - foreign key to users nullable
returned_by - foreign key to users nullable
condition_notes - text nullable
created_at, updated_at
```

#### `production_invoices`
```
id - bigint primary key
production_project_id - foreign key
invoice_number - string unique
invoice_type - enum (deposit, balance, full, final)
invoice_date - date
due_date - date
subtotal - integer (cents)
discount_amount - integer (cents)
tax_amount - integer (cents)
total - integer (cents)
amount_paid - integer (cents, default 0)
status - enum (draft, sent, partial, paid, overdue, cancelled)
sent_at - timestamp nullable
paid_at - timestamp nullable
transaction_id - foreign key to transactions nullable
payment_method - string nullable
notes - text nullable
created_at, updated_at
```

#### `production_checklists`
```
id - bigint primary key
production_project_id - foreign key
checklist_type - enum (pre_event, load_in, sound_check, event, load_out, post_event)
item - string
is_completed - boolean (default false)
completed_by - foreign key to users nullable
completed_at - timestamp nullable
notes - text nullable
position - integer
created_at, updated_at
```

#### `production_documents`
```
id - bigint primary key
production_project_id - foreign key
document_type - enum (contract, quote, invoice, stage_plot, input_list, timeline, rider, insurance, other)
filename - string
file_path - string
uploaded_by - foreign key to users
notes - text nullable
created_at, updated_at
```

#### `production_expenses`
```
id - bigint primary key
production_project_id - foreign key
expense_category - enum (equipment_rental, transportation, staffing, supplies, permits, other)
description - string
amount - integer (cents)
paid_to - string nullable
receipt_url - string nullable
notes - text nullable
created_at, updated_at
```

## Models & Relationships

### ProductionService
```php
class ProductionService extends Model
{
    use HasSlug;

    protected $casts = [
        'base_price' => 'integer',
        'hourly_rate' => 'integer',
        'minimum_hours' => 'integer',
        'included_equipment' => 'array',
        'included_staff' => 'array',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Calculate price with discount
     */
    public function calculatePrice(
        int $hours = null,
        int $discountPercentage = 0
    ): int {
        $price = $this->base_price;

        if ($this->hourly_rate && $hours) {
            $billableHours = max($hours, $this->minimum_hours ?? 1);
            $price = $this->hourly_rate * $billableHours;
        }

        if ($discountPercentage > 0) {
            $price -= (int)($price * ($discountPercentage / 100));
        }

        return $price;
    }

    /**
     * Get base price display
     */
    public function getBasePriceDisplayAttribute(): string
    {
        return '$' . number_format($this->base_price / 100, 2);
    }
}
```

### ProductionClient
```php
class ProductionClient extends Model
{
    use SoftDeletes, LogsActivity;

    protected $casts = [
        'is_crescendo_sponsor' => 'boolean',
    ];

    public function inquiries()
    {
        return $this->hasMany(ProductionInquiry::class);
    }

    public function projects()
    {
        return $this->hasMany(ProductionProject::class);
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentageAttribute(): int
    {
        return match($this->discount_tier ?? 'standard') {
            'nonprofit' => 50,
            'sponsor' => 50,
            'educational' => 30,
            default => 0,
        };
    }

    /**
     * Check if non-profit
     */
    public function isNonProfit(): bool
    {
        return $this->client_type === 'nonprofit' && $this->tax_id;
    }
}
```

### ProductionInquiry
```php
class ProductionInquiry extends Model
{
    use LogsActivity;

    protected $casts = [
        'event_date' => 'date',
        'event_time' => 'time',
        'expected_capacity' => 'integer',
        'services_requested' => 'array',
        'contacted_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(ProductionClient::class, 'production_client_id');
    }

    public function contactedBy()
    {
        return $this->belongsTo(User::class, 'contacted_by');
    }

    public function project()
    {
        return $this->belongsTo(ProductionProject::class, 'converted_to_project_id');
    }

    /**
     * Convert to project
     */
    public function convertToProject(ProductionClient $client): ProductionProject
    {
        $project = ProductionProject::create([
            'production_client_id' => $client->id,
            'project_number' => $this->generateProjectNumber(),
            'event_name' => $this->event_name,
            'event_type' => $this->event_type,
            'event_date' => $this->event_date,
            'event_start_time' => $this->event_time,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'expected_capacity' => $this->expected_capacity,
            'status' => 'quoted',
        ]);

        $this->update([
            'status' => 'converted',
            'converted_to_project_id' => $project->id,
        ]);

        return $project;
    }

    /**
     * Mark as contacted
     */
    public function markContacted(User $user): void
    {
        $this->update([
            'status' => 'contacted',
            'contacted_by' => $user->id,
            'contacted_at' => now(),
        ]);
    }

    /**
     * Generate project number
     */
    protected function generateProjectNumber(): string
    {
        $year = now()->year;
        $count = ProductionProject::whereYear('created_at', $year)->count() + 1;
        return "PROD-{$year}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
```

### ProductionProject
```php
class ProductionProject extends Model
{
    use SoftDeletes, LogsActivity, InteractsWithMedia;

    protected $casts = [
        'event_date' => 'date',
        'event_start_time' => 'time',
        'event_end_time' => 'time',
        'load_in_time' => 'time',
        'load_out_time' => 'time',
        'expected_capacity' => 'integer',
        'quoted_at' => 'datetime',
        'contracted_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(ProductionClient::class, 'production_client_id');
    }

    public function projectManager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function services()
    {
        return $this->hasMany(ProductionProjectService::class);
    }

    public function quotes()
    {
        return $this->hasMany(ProductionQuote::class)->orderBy('version');
    }

    public function contract()
    {
        return $this->hasOne(ProductionContract::class);
    }

    public function staffAssignments()
    {
        return $this->hasMany(ProductionStaffAssignment::class);
    }

    public function equipmentAssignments()
    {
        return $this->hasMany(ProductionEquipmentAssignment::class);
    }

    public function invoices()
    {
        return $this->hasMany(ProductionInvoice::class);
    }

    public function checklists()
    {
        return $this->hasMany(ProductionChecklist::class);
    }

    public function documents()
    {
        return $this->hasMany(ProductionDocument::class);
    }

    public function expenses()
    {
        return $this->hasMany(ProductionExpense::class);
    }

    /**
     * Get current quote
     */
    public function getCurrentQuoteAttribute()
    {
        return $this->quotes()->latest('version')->first();
    }

    /**
     * Get total project revenue
     */
    public function getTotalRevenueAttribute(): int
    {
        return $this->invoices()->sum('total');
    }

    /**
     * Get total expenses
     */
    public function getTotalExpensesAttribute(): int
    {
        return $this->expenses()->sum('amount');
    }

    /**
     * Get profit margin
     */
    public function getProfitMarginAttribute(): int
    {
        return $this->total_revenue - $this->total_expenses;
    }

    /**
     * Check if event is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->event_date->isFuture();
    }

    /**
     * Scope for upcoming projects
     */
    public function scopeUpcoming($query)
    {
        return $query->where('event_date', '>=', today())
            ->where('status', '!=', 'cancelled')
            ->orderBy('event_date');
    }
}
```

### ProductionQuote
```php
class ProductionQuote extends Model
{
    protected $casts = [
        'valid_until' => 'date',
        'version' => 'integer',
        'subtotal' => 'integer',
        'discount_percentage' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(ProductionProject::class, 'production_project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate quote number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quote) {
            if (!$quote->quote_number) {
                $quote->quote_number = $quote->generateQuoteNumber();
            }
        });
    }

    protected function generateQuoteNumber(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return "QUOTE-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Mark as sent
     */
    public function markSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Accept quote
     */
    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->project->update(['status' => 'contracted']);
    }

    /**
     * Check if expired
     */
    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }
}
```

### ProductionContract
```php
class ProductionContract extends Model
{
    protected $casts = [
        'deposit_percentage' => 'integer',
        'deposit_amount' => 'integer',
        'balance_amount' => 'integer',
        'payment_due_date' => 'date',
        'signed_by_client_at' => 'datetime',
        'signed_by_cmc_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(ProductionProject::class, 'production_project_id');
    }

    /**
     * Check if fully signed
     */
    public function isFullySigned(): bool
    {
        return $this->signed_by_client_at && $this->signed_by_cmc_at;
    }

    /**
     * Generate contract number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (!$contract->contract_number) {
                $contract->contract_number = $contract->generateContractNumber();
            }
        });
    }

    protected function generateContractNumber(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return "CONTRACT-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
```

### ProductionStaffAssignment
```php
class ProductionStaffAssignment extends Model
{
    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'hourly_rate' => 'integer',
        'hours_worked' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(ProductionProject::class, 'production_project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate scheduled hours
     */
    public function getScheduledHoursAttribute(): float
    {
        return $this->scheduled_start->diffInMinutes($this->scheduled_end) / 60;
    }

    /**
     * Calculate compensation
     */
    public function getCompensationAttribute(): ?int
    {
        if (!$this->hourly_rate || !$this->hours_worked) {
            return null;
        }
        return (int)($this->hourly_rate * $this->hours_worked);
    }

    /**
     * Clock in
     */
    public function clockIn(): void
    {
        $this->update([
            'actual_start' => now(),
            'status' => 'confirmed',
        ]);
    }

    /**
     * Clock out
     */
    public function clockOut(): void
    {
        $this->update([
            'actual_end' => now(),
            'status' => 'completed',
            'hours_worked' => now()->diffInMinutes($this->actual_start) / 60,
        ]);
    }
}
```

### ProductionInvoice
```php
class ProductionInvoice extends Model
{
    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'amount_paid' => 'integer',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(ProductionProject::class, 'production_project_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Generate invoice number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = $invoice->generateInvoiceNumber();
            }
        });
    }

    protected function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return "INV-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get amount due
     */
    public function getAmountDueAttribute(): int
    {
        return $this->total - $this->amount_paid;
    }

    /**
     * Check if overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && $this->status !== 'paid';
    }

    /**
     * Record payment
     */
    public function recordPayment(int $amount, ?Transaction $transaction = null): void
    {
        $this->amount_paid += $amount;

        if ($this->amount_paid >= $this->total) {
            $this->status = 'paid';
            $this->paid_at = now();
        } else {
            $this->status = 'partial';
        }

        if ($transaction) {
            $this->transaction_id = $transaction->id;
        }

        $this->save();
    }
}
```

## Service Layer

### ProductionServiceManager

```php
class ProductionServiceManager
{
    /**
     * Create quote for project
     */
    public function createQuote(
        ProductionProject $project,
        array $services,
        ?int $discountPercentage = null
    ): ProductionQuote {
        $subtotal = 0;

        // Add services to project
        foreach ($services as $serviceData) {
            $service = ProductionService::find($serviceData['service_id']);
            $hours = $serviceData['hours'] ?? null;
            $quantity = $serviceData['quantity'] ?? 1;

            $basePrice = $service->calculatePrice($hours) * $quantity;
            $clientDiscount = $discountPercentage ?? $project->client->discount_percentage;
            $discountAmt = (int)($basePrice * ($clientDiscount / 100));

            ProductionProjectService::create([
                'production_project_id' => $project->id,
                'production_service_id' => $service->id,
                'quantity' => $quantity,
                'hours' => $hours,
                'base_price' => $basePrice,
                'discount_percentage' => $clientDiscount,
                'discount_amount' => $discountAmt,
                'subtotal' => $basePrice - $discountAmt,
            ]);

            $subtotal += $basePrice - $discountAmt;
        }

        // Calculate tax (if applicable)
        $taxAmount = 0; // TODO: Tax calculation

        // Get latest version number
        $latestVersion = $project->quotes()->max('version') ?? 0;

        $quote = ProductionQuote::create([
            'production_project_id' => $project->id,
            'version' => $latestVersion + 1,
            'valid_until' => now()->addDays(30),
            'subtotal' => $subtotal,
            'discount_percentage' => $discountPercentage ?? 0,
            'discount_amount' => 0,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'terms' => $this->getDefaultTerms(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return $quote;
    }

    /**
     * Send quote to client
     */
    public function sendQuote(ProductionQuote $quote): void
    {
        // Generate PDF
        $pdf = $this->generateQuotePdf($quote);

        // Send email
        $quote->project->client->notify(new ProductionQuoteSentNotification($quote, $pdf));

        $quote->markSent();
    }

    /**
     * Create contract from accepted quote
     */
    public function createContract(ProductionQuote $quote): ProductionContract
    {
        if ($quote->status !== 'accepted') {
            throw new Exception('Quote must be accepted before creating contract');
        }

        $depositPercentage = 50;
        $depositAmount = (int)($quote->total * ($depositPercentage / 100));

        $contract = ProductionContract::create([
            'production_project_id' => $quote->project->id,
            'contract_text' => $this->generateContractText($quote),
            'deposit_percentage' => $depositPercentage,
            'deposit_amount' => $depositAmount,
            'balance_amount' => $quote->total - $depositAmount,
            'payment_due_date' => $quote->project->event_date->copy()->subDays(7),
            'cancellation_policy' => $this->getDefaultCancellationPolicy(),
            'status' => 'draft',
        ]);

        return $contract;
    }

    /**
     * Create invoice for project
     */
    public function createInvoice(
        ProductionProject $project,
        string $type,
        ?int $customAmount = null
    ): ProductionInvoice {
        $quote = $project->current_quote;
        $contract = $project->contract;

        $amount = match($type) {
            'deposit' => $contract->deposit_amount,
            'balance' => $contract->balance_amount,
            'full' => $quote->total,
            'final' => $customAmount ?? $quote->total,
        };

        return ProductionInvoice::create([
            'production_project_id' => $project->id,
            'invoice_type' => $type,
            'invoice_date' => now(),
            'due_date' => $this->calculateDueDate($type, $project),
            'subtotal' => $amount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => $amount,
            'status' => 'draft',
        ]);
    }

    /**
     * Assign staff to project
     */
    public function assignStaff(
        ProductionProject $project,
        User $user,
        string $role,
        Carbon $start,
        Carbon $end,
        ?int $hourlyRate = null
    ): ProductionStaffAssignment {
        return ProductionStaffAssignment::create([
            'production_project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'hourly_rate' => $hourlyRate,
            'status' => 'scheduled',
        ]);
    }

    /**
     * Assign equipment to project
     */
    public function assignEquipment(
        ProductionProject $project,
        Equipment $equipment,
        int $quantity = 1
    ): ProductionEquipmentAssignment {
        // Check availability
        if (!$this->isEquipmentAvailable($equipment, $project->event_date, $quantity)) {
            throw new Exception('Equipment not available for this date');
        }

        return ProductionEquipmentAssignment::create([
            'production_project_id' => $project->id,
            'equipment_id' => $equipment->id,
            'quantity' => $quantity,
            'status' => 'reserved',
        ]);
    }

    /**
     * Check equipment availability
     */
    protected function isEquipmentAvailable(
        Equipment $equipment,
        Carbon $date,
        int $quantityNeeded
    ): bool {
        $assignedQuantity = ProductionEquipmentAssignment::where('equipment_id', $equipment->id)
            ->whereHas('project', function ($query) use ($date) {
                $query->where('event_date', $date);
            })
            ->whereIn('status', ['reserved', 'loaded', 'deployed'])
            ->sum('quantity');

        $available = 1 - $assignedQuantity; // Assuming 1 of each equipment

        return $available >= $quantityNeeded;
    }

    /**
     * Generate default checklist for project
     */
    public function generateDefaultChecklists(ProductionProject $project): void
    {
        $checklists = [
            'pre_event' => [
                'Confirm client contact and timeline',
                'Review venue requirements',
                'Confirm staff assignments',
                'Check equipment inventory',
                'Confirm load-in time with venue',
                'Send reminder to client (48 hours)',
            ],
            'load_in' => [
                'Arrive at load-in time',
                'Meet venue contact',
                'Assess power and staging',
                'Unload equipment',
                'Set up PA system',
                'Run cables',
                'Position monitors',
            ],
            'sound_check' => [
                'Test all inputs',
                'Set monitor mixes',
                'Test microphones',
                'Set main mix levels',
                'Check for feedback',
                'Test emergency protocols',
            ],
            'event' => [
                'Monitor sound levels',
                'Adjust as needed',
                'Manage transitions',
                'Handle client requests',
                'Document any issues',
            ],
            'load_out' => [
                'Strike PA system',
                'Coil cables properly',
                'Pack equipment',
                'Load truck',
                'Venue walkthrough',
                'Get client signature',
            ],
            'post_event' => [
                'Return equipment to storage',
                'Check equipment condition',
                'Submit expense receipts',
                'Send invoice to client',
                'Request client feedback',
                'Update project notes',
            ],
        ];

        foreach ($checklists as $type => $items) {
            foreach ($items as $index => $item) {
                ProductionChecklist::create([
                    'production_project_id' => $project->id,
                    'checklist_type' => $type,
                    'item' => $item,
                    'position' => $index + 1,
                ]);
            }
        }
    }

    /**
     * Calculate project profitability
     */
    public function calculateProfitability(ProductionProject $project): array
    {
        $revenue = $project->total_revenue;
        $expenses = $project->total_expenses;

        // Calculate staff costs
        $staffCosts = $project->staffAssignments()
            ->whereNotNull('hours_worked')
            ->get()
            ->sum('compensation');

        $totalCosts = $expenses + $staffCosts;
        $profit = $revenue - $totalCosts;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        return [
            'revenue' => $revenue,
            'direct_expenses' => $expenses,
            'staff_costs' => $staffCosts,
            'total_costs' => $totalCosts,
            'profit' => $profit,
            'margin_percentage' => round($margin, 2),
        ];
    }

    /**
     * Get default terms
     */
    protected function getDefaultTerms(): string
    {
        return "50% deposit due upon contract signing. Balance due 7 days before event. Cancellations within 14 days forfeit deposit.";
    }

    /**
     * Get default cancellation policy
     */
    protected function getDefaultCancellationPolicy(): string
    {
        return "Cancellations more than 30 days before event: full refund. 15-30 days: 50% refund. Less than 14 days: no refund.";
    }

    /**
     * Calculate due date
     */
    protected function calculateDueDate(string $invoiceType, ProductionProject $project): Carbon
    {
        return match($invoiceType) {
            'deposit' => now()->addDays(7),
            'balance' => $project->event_date->copy()->subDays(7),
            'full', 'final' => now()->addDays(30),
        };
    }

    /**
     * Generate quote PDF
     */
    protected function generateQuotePdf(ProductionQuote $quote): string
    {
        $pdf = PDF::loadView('production.quote-pdf', compact('quote'));
        $filename = "{$quote->quote_number}.pdf";
        $path = storage_path('app/production-quotes/' . $filename);
        $pdf->save($path);
        return $path;
    }

    /**
     * Generate contract text
     */
    protected function generateContractText(ProductionQuote $quote): string
    {
        return view('production.contract-template', compact('quote'))->render();
    }
}
```

## Filament Resources

### ProductionInquiryResource
- Location: `/member/production/inquiries`
- Inquiry queue
- Contact status tracking
- Convert to project action
- Filter by status, date

### ProductionProjectResource
- Location: `/member/production/projects`
- Project pipeline (Kanban view)
- Quote generation
- Contract management
- Staff scheduling
- Equipment assignments
- Invoice generation
- Timeline view

### ProductionClientResource
- Location: `/member/production/clients`
- Client directory
- Project history
- Discount tier management
- Contact information

### ProductionServiceResource
- Location: `/member/production/services`
- Service catalog
- Pricing management
- Package configuration

### ProductionStaffScheduleResource
- Location: `/member/production/schedule`
- Calendar view of staff assignments
- Availability tracking
- Hour logging
- Compensation tracking

### ProductionEquipmentResource
- Location: `/member/production/equipment`
- Equipment inventory
- Availability calendar
- Maintenance tracking
- Assignment history

## Widgets

### UpcomingProjectsWidget
- Next 5 projects
- Status indicators
- Quick actions

### InquiryQueueWidget
- New inquiries count
- Follow-up reminders
- Conversion rate

### RevenueProjectionsWidget
- Projected revenue (quoted + contracted)
- Month-over-month trends
- Average project value

### EquipmentUtilizationWidget
- Equipment usage rates
- Most requested items
- Availability conflicts

## Commands

### Send Project Reminders
```bash
php artisan production:send-reminders
```
- Client reminders (deposit, balance due)
- Staff reminders (upcoming assignments)
- Equipment checkout reminders

### Generate Invoices
```bash
php artisan production:generate-invoices [--type=deposit|balance]
```
- Auto-generate invoices based on dates
- Send to clients
- Track in system

### Check Equipment Availability
```bash
php artisan production:check-equipment-availability [--date=]
```
- Report on equipment conflicts
- Suggest alternatives
- Alert project managers

## Notifications

### ProductionInquiryReceivedNotification
- Sent to production team
- Inquiry details
- Quick response actions

### ProductionQuoteSentNotification
- Sent to client
- Quote PDF attachment
- Accept/decline links

### ProductionContractSignedNotification
- Sent to production team
- Contract details
- Next steps

### ProductionDepositDueNotification
- Sent to client
- Payment instructions
- Invoice link

### ProductionEventReminderNotification
- Sent to staff 48 hours before
- Event details
- Load-in time and location

### ProductionInvoiceOverdueNotification
- Sent to client
- Amount due
- Payment options

## Integration Points

### Equipment System
- Reserve equipment for projects
- Track usage and condition
- Maintenance scheduling
- Checkout/return workflow

### Volunteer System
- Staff assignments for volunteers
- Hour tracking
- Skill matching

### Productions (Events)
- Link production services to CMC shows
- Internal production tracking
- Resource allocation

### Transactions
- Invoice payments
- Deposit tracking
- Revenue reporting
- Client payment history

## Pricing Summary

### Standard Packages

**Basic Sound** - $500
- 150-person PA
- 1 engineer (4 hours)
- Basic mic package

**Standard Sound** - $800
- 250-person PA
- 1 engineer (6 hours)
- Full mic package

**Premium** - $1,500
- 300-person PA
- Lead + assistant
- 8-hour production

**Booking Coordination** - $250-$500
**Event Consultation** - $100/hour

### Discounts
- **Non-Profit**: 50% off
- **Crescendo Sponsors**: 50% off
- **Educational**: 30% off
- **Multi-Event**: 10-20% off

## Implementation Estimates

### Phase 1: Core Models & Client Management (18-24 hours)
- Database migrations
- Models and relationships
- ProductionServiceManager basics
- Client and inquiry management

### Phase 2: Quote & Contract System (16-22 hours)
- Quote generation
- PDF export
- Contract templates
- E-signature integration

### Phase 3: Staff Scheduling (14-18 hours)
- Staff assignments
- Calendar integration
- Hour tracking
- Compensation calculation

### Phase 4: Equipment Integration (12-16 hours)
- Equipment assignments
- Availability checking
- Checkout/return workflow
- Conflict detection

### Phase 5: Invoicing & Payments (14-18 hours)
- Invoice generation
- Payment tracking
- Transaction integration
- Overdue notifications

### Phase 6: Filament Resources (22-28 hours)
- All admin resources
- Project pipeline (Kanban)
- Client portal
- Staff schedule views

### Phase 7: Checklists & Workflows (10-14 hours)
- Template checklists
- Status automation
- Reminders and notifications
- Document management

### Phase 8: Analytics & Reporting (10-14 hours)
- Profitability reports
- Utilization metrics
- Revenue projections
- Client reports

### Phase 9: Testing & Polish (10-14 hours)
- Feature tests
- Workflow testing
- PDF generation tests
- Documentation

**Total Estimate: 126-168 hours**

## Future Enhancements

### Advanced Features
- Mobile app for field staff
- Real-time equipment tracking (GPS)
- Client self-service portal
- Automated quote generation from templates
- AI-powered pricing optimization
- Predictive equipment maintenance
- Weather integration for outdoor events

### Service Expansion
- Video production services
- Recording services
- Streaming/broadcast support
- Lighting design and operation
- Stage management
- Festival production packages

### Business Intelligence
- Market analysis and pricing trends
- Competitor tracking
- Client lifetime value analysis
- Service profitability by type
- Staff productivity metrics
- Equipment ROI tracking

### Integration
- QuickBooks/Xero accounting sync
- Calendar sync (Google/Outlook)
- CRM integration (HubSpot, Salesforce)
- Ticketing platform integration
- Insurance and liability tracking
- Venue database with specs
