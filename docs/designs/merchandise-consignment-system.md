# Band Merchandise Consignment System Design

## Overview

A comprehensive consignment and retail management system for selling **CMC member band** merchandise through CMC's physical space, events, and online store. Supports both consignment (70/30 split) and wholesale purchase models, with full inventory tracking, sales reporting, multi-channel POS integration, and automated payouts. Only bands with active members in the CMC community can participate.

## Goals

- **Multi-Channel Sales**: Events, physical space, online store
- **Inventory Management**: Real-time tracking across all channels
- **Consignment Tracking**: Per-item ownership, sales splits, payouts
- **Wholesale Purchases**: Buy band inventory outright at wholesale pricing
- **POS Integration**: Mobile and fixed point-of-sale systems
- **Band Portal**: Self-service inventory, sales reports, payout history
- **Automated Payouts**: Monthly settlements with detailed reporting
- **Marketing Integration**: Featured products, promotions, cross-selling

## Core Features

### 1. Merchandise Management
- Product catalog with variants (sizes, colors)
- Consignment vs. wholesale tracking
- Per-item ownership and pricing
- Inventory across multiple locations
- Product photography and descriptions
- SKU/barcode generation

### 2. Consignment Program
- Band application and onboarding (CMC bands only)
- Applicant must be an active band member
- Inventory submission and acceptance
- 70/30 revenue split (band/CMC)
- Monthly payout calculations
- Sales reporting per band
- Unsold inventory returns

### 3. Wholesale Purchasing
- Bulk purchase from bands at wholesale price
- CMC owns inventory outright
- Full retail margin to CMC
- Quick backstock clearance for bands

### 4. Multi-Channel Sales
- Event sales (mobile POS)
- Physical retail space (fixed POS)
- Online store integration
- Member discounts (10% sustaining member discount)
- Gift card support

### 5. Inventory Tracking
- Real-time stock levels per location
- Transfer between locations (events ↔ retail space)
- Restock alerts and requests
- Inventory reconciliation
- Loss/damage tracking

### 6. Financial Management
- Revenue split calculations
- Stripe fee allocation
- Monthly payout generation
- Transaction history
- Sales tax handling
- Refund processing

### 7. Analytics & Reporting
- Sales by band, product, channel
- Inventory turnover rates
- Popular items and trends
- Payout summaries
- Band performance metrics

## Database Schema

### Tables

#### `merchandise_bands`
```
id - bigint primary key
band_id - foreign key to bands (required - CMC bands only)
contact_user_id - foreign key to users (must be band member)
contact_email - string
contact_phone - string nullable
status - enum (pending, active, inactive, suspended)
application_notes - text nullable
approved_by - foreign key to users nullable
approved_at - timestamp nullable
payout_method - enum (stripe_transfer, check, account_credit)
payout_schedule - enum (monthly, quarterly, on_demand)
default_revenue_split - integer (default 70, percentage to band)
settings - json
created_at, updated_at, deleted_at
unique (band_id)
```

#### `merchandise_products`
```
id - bigint primary key
merchandise_band_id - foreign key
name - string
slug - string unique
description - text
product_type - enum (apparel, music, stickers, posters, accessories, other)
ownership_type - enum (consignment, wholesale, cmc_owned)
base_price - integer (cents, retail price)
wholesale_price - integer nullable (cents, what CMC paid if wholesale)
cost_basis - integer nullable (cents, for CMC-owned products)
is_active - boolean (default true)
featured - boolean (default false)
tags - json (genres, event types, etc.)
created_at, updated_at, deleted_at
```

#### `merchandise_variants`
```
id - bigint primary key
merchandise_product_id - foreign key
sku - string unique
barcode - string nullable unique
variant_name - string (e.g., "L / Black", "CD", "Poster")
size - string nullable
color - string nullable
price_adjustment - integer (cents, +/- from base price)
is_active - boolean (default true)
created_at, updated_at
```

#### `merchandise_inventory`
```
id - bigint primary key
merchandise_variant_id - foreign key
location_type - enum (retail_space, event_stock, storage, consignment_hold)
location_id - bigint nullable (event_id if at event)
quantity - integer
reorder_threshold - integer nullable
notes - text nullable
last_counted_at - timestamp nullable
created_at, updated_at
```

#### `merchandise_inventory_movements`
```
id - bigint primary key
merchandise_variant_id - foreign key
movement_type - enum (incoming, sale, return, transfer, adjustment, loss, damage)
quantity - integer (positive or negative)
from_location_type - string nullable
from_location_id - bigint nullable
to_location_type - string nullable
to_location_id - bigint nullable
reference_type - string nullable (MerchandiseSale, etc.)
reference_id - bigint nullable
notes - text nullable
performed_by - foreign key to users nullable
created_at
```

#### `merchandise_sales`
```
id - bigint primary key
transaction_id - foreign key to transactions
sale_channel - enum (event, retail_space, online, other)
sold_at_event_id - foreign key nullable (Production, CommunityEvent)
sold_by - foreign key to users nullable
customer_user_id - foreign key to users nullable (if member)
customer_name - string nullable
customer_email - string nullable
subtotal - integer (cents, before discount)
discount_amount - integer (cents)
tax_amount - integer (cents)
total_amount - integer (cents)
payment_method - enum (card, cash, account_credit, gift_card)
payment_status - enum (pending, completed, refunded, failed)
refunded_at - timestamp nullable
refund_reason - text nullable
notes - text nullable
created_at, updated_at
```

#### `merchandise_sale_items`
```
id - bigint primary key
merchandise_sale_id - foreign key
merchandise_variant_id - foreign key
merchandise_band_id - foreign key (denormalized for reporting)
ownership_type - enum (consignment, wholesale, cmc_owned)
quantity - integer
unit_price - integer (cents, actual sale price)
unit_cost - integer nullable (cents, what CMC paid if applicable)
revenue_split_percentage - integer (band's percentage if consignment)
band_revenue - integer (cents, what band earns)
cmc_revenue - integer (cents, what CMC earns)
stripe_fee - integer (cents, allocated portion of fees)
created_at, updated_at
```

#### `merchandise_payouts`
```
id - bigint primary key
merchandise_band_id - foreign key
period_start - date
period_end - date
total_sales - integer (cents, band's portion)
stripe_fees - integer (cents, band's portion of fees)
adjustment_amount - integer (cents, +/- for corrections)
net_payout - integer (cents, final payout amount)
payout_method - enum (stripe_transfer, check, account_credit)
status - enum (pending, processing, completed, failed)
stripe_transfer_id - string nullable
processed_at - timestamp nullable
processed_by - foreign key to users nullable
notes - text nullable
created_at, updated_at
```

#### `merchandise_consignment_terms`
```
id - bigint primary key
merchandise_band_id - foreign key
merchandise_product_id - foreign key nullable (specific product or default)
revenue_split_percentage - integer (band's percentage)
minimum_price - integer nullable (cents)
consignment_duration_days - integer (default 180)
return_policy - text
effective_from - date
effective_until - date nullable
created_at, updated_at
```

#### `merchandise_applications`
```
id - bigint primary key
band_id - foreign key to bands (required - CMC bands only)
applicant_user_id - foreign key to users (must be band member)
contact_email - string
sample_products - json (names, prices, photos)
inventory_commitment - text
wholesale_interest - boolean (willing to sell wholesale)
additional_notes - text nullable
status - enum (pending, approved, rejected, withdrawn)
reviewed_by - foreign key to users nullable
reviewed_at - timestamp nullable
review_notes - text nullable
created_at, updated_at
unique (band_id) where status = 'pending'
```

#### `merchandise_transfers`
```
id - bigint primary key
from_location_type - string
from_location_id - bigint nullable
to_location_type - string
to_location_id - bigint nullable
transfer_type - enum (event_prep, event_return, restock, storage)
status - enum (pending, in_transit, completed, cancelled)
requested_by - foreign key to users
approved_by - foreign key to users nullable
completed_by - foreign key to users nullable
requested_at - timestamp
completed_at - timestamp nullable
notes - text nullable
created_at, updated_at
```

#### `merchandise_transfer_items`
```
id - bigint primary key
merchandise_transfer_id - foreign key
merchandise_variant_id - foreign key
quantity_requested - integer
quantity_transferred - integer
notes - text nullable
created_at, updated_at
```

## Models & Relationships

### MerchandiseBand
```php
class MerchandiseBand extends Model
{
    use SoftDeletes, LogsActivity;

    protected $casts = [
        'approved_at' => 'datetime',
        'default_revenue_split' => 'integer',
        'settings' => 'array',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    public function contact()
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function products()
    {
        return $this->hasMany(MerchandiseProduct::class);
    }

    public function payouts()
    {
        return $this->hasMany(MerchandisePayout::class);
    }

    public function consignmentTerms()
    {
        return $this->hasMany(MerchandiseConsignmentTerm::class);
    }

    /**
     * Get band display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->band->name;
    }

    /**
     * Check if contact is a band member
     */
    public function contactIsBandMember(): bool
    {
        return $this->band->members()->where('user_id', $this->contact_user_id)->exists();
    }

    /**
     * Get active products count
     */
    public function getActiveProductsCountAttribute(): int
    {
        return $this->products()->where('is_active', true)->count();
    }

    /**
     * Get total sales this period
     */
    public function getSalesForPeriod(Carbon $start, Carbon $end): int
    {
        return MerchandiseSaleItem::where('merchandise_band_id', $this->id)
            ->whereHas('sale', function ($query) use ($start, $end) {
                $query->whereBetween('created_at', [$start, $end])
                    ->where('payment_status', 'completed');
            })
            ->sum('band_revenue');
    }

    /**
     * Check if active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
```

### MerchandiseProduct
```php
class MerchandiseProduct extends Model
{
    use SoftDeletes, LogsActivity, HasSlug, HasTags, InteractsWithMedia;

    protected $casts = [
        'base_price' => 'integer',
        'wholesale_price' => 'integer',
        'cost_basis' => 'integer',
        'is_active' => 'boolean',
        'featured' => 'boolean',
        'tags' => 'array',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function band()
    {
        return $this->belongsTo(MerchandiseBand::class, 'merchandise_band_id');
    }

    public function variants()
    {
        return $this->hasMany(MerchandiseVariant::class);
    }

    /**
     * Get total inventory across all variants
     */
    public function getTotalInventoryAttribute(): int
    {
        return $this->variants()
            ->with('inventory')
            ->get()
            ->sum(fn($variant) => $variant->total_quantity);
    }

    /**
     * Get revenue split percentage
     */
    public function getRevenueSplitAttribute(): int
    {
        if ($this->ownership_type !== 'consignment') {
            return 0; // Wholesale/CMC-owned = 100% to CMC
        }

        return $this->band->default_revenue_split ?? 70;
    }

    /**
     * Calculate pricing breakdown
     */
    public function calculateRevenue(int $quantity = 1, bool $memberDiscount = false): array
    {
        $price = $this->base_price;

        if ($memberDiscount) {
            $price = (int)($price * 0.9); // 10% member discount
        }

        $subtotal = $price * $quantity;
        $stripeFee = (int)(($subtotal * 0.029) + 30); // 2.9% + $0.30

        if ($this->ownership_type === 'consignment') {
            $bandRevenue = (int)($subtotal * ($this->revenue_split / 100));
            $cmcRevenue = $subtotal - $bandRevenue - $stripeFee;
        } else {
            $bandRevenue = 0;
            $cmcRevenue = $subtotal - $stripeFee;
        }

        return [
            'subtotal' => $subtotal,
            'stripe_fee' => $stripeFee,
            'band_revenue' => $bandRevenue,
            'cmc_revenue' => $cmcRevenue,
            'member_discount_applied' => $memberDiscount,
        ];
    }

    /**
     * Get primary image
     */
    public function getImageUrlAttribute()
    {
        return $this->getFirstMediaUrl('product_images');
    }
}
```

### MerchandiseVariant
```php
class MerchandiseVariant extends Model
{
    protected $casts = [
        'price_adjustment' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(MerchandiseProduct::class, 'merchandise_product_id');
    }

    public function inventory()
    {
        return $this->hasMany(MerchandiseInventory::class);
    }

    public function movements()
    {
        return $this->hasMany(MerchandiseInventoryMovement::class);
    }

    /**
     * Get final price including adjustment
     */
    public function getFinalPriceAttribute(): int
    {
        return $this->product->base_price + ($this->price_adjustment ?? 0);
    }

    /**
     * Get total quantity across all locations
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->inventory()->sum('quantity');
    }

    /**
     * Get quantity at specific location
     */
    public function getQuantityAt(string $locationType, ?int $locationId = null): int
    {
        return $this->inventory()
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->sum('quantity');
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->product->name . ' - ' . $this->variant_name;
    }

    /**
     * Check if in stock
     */
    public function isInStock(?string $locationType = null): bool
    {
        if ($locationType) {
            return $this->getQuantityAt($locationType) > 0;
        }
        return $this->total_quantity > 0;
    }
}
```

### MerchandiseInventory
```php
class MerchandiseInventory extends Model
{
    protected $casts = [
        'quantity' => 'integer',
        'reorder_threshold' => 'integer',
        'last_counted_at' => 'datetime',
    ];

    public function variant()
    {
        return $this->belongsTo(MerchandiseVariant::class, 'merchandise_variant_id');
    }

    /**
     * Check if reorder needed
     */
    public function needsReorder(): bool
    {
        return $this->reorder_threshold && $this->quantity <= $this->reorder_threshold;
    }
}
```

### MerchandiseInventoryMovement
```php
class MerchandiseInventoryMovement extends Model
{
    public $timestamps = false;

    protected $casts = [
        'quantity' => 'integer',
        'from_location_id' => 'integer',
        'to_location_id' => 'integer',
        'reference_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function variant()
    {
        return $this->belongsTo(MerchandiseVariant::class, 'merchandise_variant_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
```

### MerchandiseSale
```php
class MerchandiseSale extends Model
{
    use LogsActivity;

    protected $casts = [
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'refunded_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function items()
    {
        return $this->hasMany(MerchandiseSaleItem::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function event()
    {
        return $this->morphTo('sold_at_event');
    }

    /**
     * Check if refundable
     */
    public function isRefundable(): bool
    {
        return $this->payment_status === 'completed' &&
               !$this->refunded_at &&
               $this->created_at->isAfter(now()->subDays(30));
    }

    /**
     * Process refund
     */
    public function refund(string $reason): void
    {
        $this->update([
            'payment_status' => 'refunded',
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);

        // Restore inventory
        foreach ($this->items as $item) {
            app(MerchandiseService::class)->adjustInventory(
                $item->variant,
                'return',
                $item->quantity,
                'retail_space',
                null,
                "Refund: {$this->id}"
            );
        }
    }
}
```

### MerchandiseSaleItem
```php
class MerchandiseSaleItem extends Model
{
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'unit_cost' => 'integer',
        'revenue_split_percentage' => 'integer',
        'band_revenue' => 'integer',
        'cmc_revenue' => 'integer',
        'stripe_fee' => 'integer',
    ];

    public function sale()
    {
        return $this->belongsTo(MerchandiseSale::class);
    }

    public function variant()
    {
        return $this->belongsTo(MerchandiseVariant::class, 'merchandise_variant_id');
    }

    public function band()
    {
        return $this->belongsTo(MerchandiseBand::class, 'merchandise_band_id');
    }

    /**
     * Get total line amount
     */
    public function getTotalAttribute(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
```

### MerchandisePayout
```php
class MerchandisePayout extends Model
{
    use LogsActivity;

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_sales' => 'integer',
        'stripe_fees' => 'integer',
        'adjustment_amount' => 'integer',
        'net_payout' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function band()
    {
        return $this->belongsTo(MerchandiseBand::class, 'merchandise_band_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Check if pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Mark as completed
     */
    public function markCompleted(User $user, ?string $transferId = null): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
            'processed_by' => $user->id,
            'stripe_transfer_id' => $transferId,
        ]);
    }
}
```

### MerchandiseApplication
```php
class MerchandiseApplication extends Model
{
    use LogsActivity;

    protected $casts = [
        'sample_products' => 'array',
        'wholesale_interest' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Approve application
     */
    public function approve(User $reviewer): MerchandiseBand
    {
        // Verify applicant is a band member
        if (!$this->band->members()->where('user_id', $this->applicant_user_id)->exists()) {
            throw new Exception('Applicant must be a member of the band');
        }

        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return MerchandiseBand::create([
            'band_id' => $this->band_id,
            'contact_user_id' => $this->applicant_user_id,
            'contact_email' => $this->contact_email,
            'status' => 'active',
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject application
     */
    public function reject(User $reviewer, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);
    }
}
```

### MerchandiseTransfer
```php
class MerchandiseTransfer extends Model
{
    use LogsActivity;

    protected $casts = [
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(MerchandiseTransferItem::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Complete transfer
     */
    public function complete(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach ($this->items as $item) {
                app(MerchandiseService::class)->transferInventory(
                    $item->variant,
                    $this->from_location_type,
                    $this->from_location_id,
                    $this->to_location_type,
                    $this->to_location_id,
                    $item->quantity_transferred,
                    "Transfer #{$this->id}"
                );
            }

            $this->update([
                'status' => 'completed',
                'completed_by' => $user->id,
                'completed_at' => now(),
            ]);
        });
    }
}
```

## Service Layer

### MerchandiseService

```php
class MerchandiseService
{
    /**
     * Process merchandise sale
     */
    public function processSale(
        array $items, // [{variant_id, quantity}, ...]
        string $channel,
        ?User $customer = null,
        ?User $seller = null,
        ?Model $event = null,
        bool $memberDiscount = false
    ): MerchandiseSale {
        return DB::transaction(function () use ($items, $channel, $customer, $seller, $event, $memberDiscount) {
            $subtotal = 0;
            $discountAmount = 0;
            $saleItems = [];

            // Calculate totals and prepare items
            foreach ($items as $item) {
                $variant = MerchandiseVariant::findOrFail($item['variant_id']);
                $product = $variant->product;
                $quantity = $item['quantity'];

                $pricing = $product->calculateRevenue($quantity, $memberDiscount);

                $subtotal += $pricing['subtotal'];
                if ($memberDiscount) {
                    $discountAmount += ($variant->final_price * 0.1 * $quantity);
                }

                $saleItems[] = [
                    'variant' => $variant,
                    'product' => $product,
                    'quantity' => $quantity,
                    'pricing' => $pricing,
                ];
            }

            $total = $subtotal - $discountAmount;

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => $customer?->id,
                'amount' => $total,
                'type' => 'merchandise_sale',
                'status' => 'completed',
                'description' => "Merchandise purchase ({$channel})",
            ]);

            // Create sale
            $sale = MerchandiseSale::create([
                'transaction_id' => $transaction->id,
                'sale_channel' => $channel,
                'sold_at_event_type' => $event ? get_class($event) : null,
                'sold_at_event_id' => $event?->id,
                'sold_by' => $seller?->id,
                'customer_user_id' => $customer?->id,
                'customer_name' => $customer?->name,
                'customer_email' => $customer?->email,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => 0, // TODO: Tax calculation
                'total_amount' => $total,
                'payment_method' => 'card',
                'payment_status' => 'completed',
            ]);

            // Create sale items and adjust inventory
            foreach ($saleItems as $item) {
                $pricing = $item['pricing'];

                MerchandiseSaleItem::create([
                    'merchandise_sale_id' => $sale->id,
                    'merchandise_variant_id' => $item['variant']->id,
                    'merchandise_band_id' => $item['product']->merchandise_band_id,
                    'ownership_type' => $item['product']->ownership_type,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['variant']->final_price,
                    'unit_cost' => $item['product']->wholesale_price ?? $item['product']->cost_basis,
                    'revenue_split_percentage' => $item['product']->revenue_split,
                    'band_revenue' => $pricing['band_revenue'],
                    'cmc_revenue' => $pricing['cmc_revenue'],
                    'stripe_fee' => $pricing['stripe_fee'],
                ]);

                // Deduct inventory
                $this->adjustInventory(
                    $item['variant'],
                    'sale',
                    -$item['quantity'],
                    $channel === 'event' ? 'event_stock' : 'retail_space',
                    $event?->id,
                    "Sale #{$sale->id}",
                    $sale
                );
            }

            return $sale;
        });
    }

    /**
     * Adjust inventory and create movement record
     */
    public function adjustInventory(
        MerchandiseVariant $variant,
        string $movementType,
        int $quantity,
        string $locationType,
        ?int $locationId = null,
        ?string $notes = null,
        ?Model $reference = null,
        ?User $performedBy = null
    ): void {
        DB::transaction(function () use ($variant, $movementType, $quantity, $locationType, $locationId, $notes, $reference, $performedBy) {
            // Update inventory
            $inventory = MerchandiseInventory::firstOrCreate(
                [
                    'merchandise_variant_id' => $variant->id,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                ],
                ['quantity' => 0]
            );

            $inventory->increment('quantity', $quantity);

            // Record movement
            MerchandiseInventoryMovement::create([
                'merchandise_variant_id' => $variant->id,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'to_location_type' => $locationType,
                'to_location_id' => $locationId,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
                'notes' => $notes,
                'performed_by' => $performedBy?->id ?? auth()->id(),
            ]);
        });
    }

    /**
     * Transfer inventory between locations
     */
    public function transferInventory(
        MerchandiseVariant $variant,
        string $fromLocationType,
        ?int $fromLocationId,
        string $toLocationType,
        ?int $toLocationId,
        int $quantity,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($variant, $fromLocationType, $fromLocationId, $toLocationType, $toLocationId, $quantity, $notes) {
            // Deduct from source
            $fromInventory = MerchandiseInventory::where('merchandise_variant_id', $variant->id)
                ->where('location_type', $fromLocationType)
                ->where('location_id', $fromLocationId)
                ->firstOrFail();

            if ($fromInventory->quantity < $quantity) {
                throw new Exception('Insufficient inventory at source location');
            }

            $fromInventory->decrement('quantity', $quantity);

            // Add to destination
            $toInventory = MerchandiseInventory::firstOrCreate(
                [
                    'merchandise_variant_id' => $variant->id,
                    'location_type' => $toLocationType,
                    'location_id' => $toLocationId,
                ],
                ['quantity' => 0]
            );

            $toInventory->increment('quantity', $quantity);

            // Record movement
            MerchandiseInventoryMovement::create([
                'merchandise_variant_id' => $variant->id,
                'movement_type' => 'transfer',
                'quantity' => $quantity,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'notes' => $notes,
                'performed_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Generate monthly payouts
     */
    public function generateMonthlyPayouts(?Carbon $month = null): Collection
    {
        $month = $month ?? now()->subMonth();
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $payouts = collect();

        $activeBands = MerchandiseBand::where('status', 'active')->get();

        foreach ($activeBands as $band) {
            $salesData = $this->calculateBandSales($band, $periodStart, $periodEnd);

            if ($salesData['total_sales'] <= 0) {
                continue; // No sales this period
            }

            $payout = MerchandisePayout::create([
                'merchandise_band_id' => $band->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_sales' => $salesData['total_sales'],
                'stripe_fees' => $salesData['stripe_fees'],
                'adjustment_amount' => 0,
                'net_payout' => $salesData['total_sales'] - $salesData['stripe_fees'],
                'payout_method' => $band->payout_method,
                'status' => 'pending',
            ]);

            $payouts->push($payout);

            // Notify band
            $band->contact->notify(new MerchandisePayoutReadyNotification($payout));
        }

        return $payouts;
    }

    /**
     * Calculate band sales for period
     */
    protected function calculateBandSales(MerchandiseBand $band, Carbon $start, Carbon $end): array
    {
        $items = MerchandiseSaleItem::where('merchandise_band_id', $band->id)
            ->whereHas('sale', function ($query) use ($start, $end) {
                $query->whereBetween('created_at', [$start, $end])
                    ->where('payment_status', 'completed');
            })
            ->get();

        return [
            'total_sales' => $items->sum('band_revenue'),
            'stripe_fees' => $items->sum('stripe_fee'),
            'item_count' => $items->sum('quantity'),
        ];
    }

    /**
     * Process payout via Stripe
     */
    public function processPayout(MerchandisePayout $payout, User $processor): void
    {
        if ($payout->status !== 'pending') {
            throw new Exception('Payout is not pending');
        }

        // TODO: Implement Stripe Connect transfer
        // $transfer = Stripe\Transfer::create([...]);

        $payout->markCompleted($processor, null); // Pass Stripe transfer ID

        // Create transaction record
        Transaction::create([
            'user_id' => $payout->band->contact_user_id,
            'amount' => $payout->net_payout,
            'type' => 'merchandise_payout',
            'status' => 'completed',
            'description' => "Merchandise payout: {$payout->period_start->format('M Y')}",
        ]);
    }

    /**
     * Get band sales report
     */
    public function getBandSalesReport(MerchandiseBand $band, Carbon $start, Carbon $end): array
    {
        $items = MerchandiseSaleItem::where('merchandise_band_id', $band->id)
            ->whereHas('sale', function ($query) use ($start, $end) {
                $query->whereBetween('created_at', [$start, $end])
                    ->where('payment_status', 'completed');
            })
            ->with(['variant.product', 'sale'])
            ->get();

        $byProduct = $items->groupBy(fn($item) => $item->variant->product->name);

        return [
            'period' => [
                'start' => $start,
                'end' => $end,
            ],
            'totals' => [
                'units_sold' => $items->sum('quantity'),
                'gross_sales' => $items->sum(fn($item) => $item->unit_price * $item->quantity),
                'band_revenue' => $items->sum('band_revenue'),
                'cmc_revenue' => $items->sum('cmc_revenue'),
                'stripe_fees' => $items->sum('stripe_fee'),
            ],
            'by_product' => $byProduct->map(fn($items) => [
                'units_sold' => $items->sum('quantity'),
                'revenue' => $items->sum('band_revenue'),
            ]),
            'by_channel' => $items->groupBy('sale.sale_channel')->map(fn($items) => [
                'units_sold' => $items->sum('quantity'),
                'revenue' => $items->sum('band_revenue'),
            ]),
            'recent_sales' => $items->sortByDesc('sale.created_at')->take(10)->values(),
        ];
    }

    /**
     * Get inventory alerts
     */
    public function getInventoryAlerts(): Collection
    {
        return MerchandiseInventory::whereHas('variant.product', fn($q) => $q->where('is_active', true))
            ->get()
            ->filter(fn($inv) => $inv->needsReorder())
            ->map(function ($inv) {
                return [
                    'variant' => $inv->variant,
                    'product' => $inv->variant->product,
                    'band' => $inv->variant->product->band,
                    'location' => $inv->location_type,
                    'current_quantity' => $inv->quantity,
                    'reorder_threshold' => $inv->reorder_threshold,
                ];
            });
    }
}
```

## Filament Resources

### MerchandiseBandResource
- Location: `/member/merchandise/bands`
- Application review queue (validates band membership)
- Active CMC bands management
- Sales reports per band
- Payout history
- **Note**: Only shows bands in the CMC Band directory

### MerchandiseProductResource
- Location: `/member/merchandise/products`
- Product catalog with variants
- Inventory levels per location
- Pricing and ownership type
- Image gallery management

### MerchandiseSaleResource
- Location: `/member/merchandise/sales`
- Sales history with filtering
- Refund processing
- Receipt generation
- Analytics dashboard

### MerchandiseInventoryResource
- Location: `/member/merchandise/inventory`
- Stock levels by location
- Transfer requests
- Reorder alerts
- Physical count reconciliation

### MerchandisePayoutResource
- Location: `/member/merchandise/payouts`
- Pending payouts queue
- Process payments
- Payout history
- Band-specific views

## POS Interface

### Mobile POS (Events)
- Quick product search/scan
- Shopping cart
- Member lookup for discounts
- Card payment integration (Stripe Terminal)
- Cash payment tracking
- Receipt email

### Fixed POS (Retail Space)
- Full catalog browsing
- Member account integration
- Gift card support
- Returns/exchanges
- End-of-day reporting

## Band Portal

**Access Control**: Only band members can access their band's merchandise portal

### Dashboard
- Sales overview (current month)
- Top products
- Upcoming payout
- Inventory alerts

### Products
- Add/edit products
- Upload photos
- Manage variants
- Set pricing

### Sales Reports
- Filterable by date, product, channel
- Export to CSV
- Visual charts

### Payouts
- Payout history
- Detailed breakdowns
- Tax documents (1099-MISC)

**Permission Check**: User must be in band's `band_members` relationship to access

## Public Pages

### Merchandise Store
- `/store` - Browse all merchandise
- Filter by band, type, price
- Product detail pages
- Add to cart
- Member login for discount
- Checkout with Stripe

### Band Merchandise Pages
- `/store/bands/{slug}` - All products from one band
- Band bio and links
- Upcoming shows

## Commands

### Generate Payouts
```bash
php artisan merchandise:generate-payouts [--month=YYYY-MM]
```
- Calculate monthly payouts
- Create payout records
- Send notifications

### Process Payouts
```bash
php artisan merchandise:process-payouts [--dry-run]
```
- Execute Stripe transfers
- Update payout status
- Create transaction records

### Inventory Alerts
```bash
php artisan merchandise:inventory-alerts
```
- Send restock notifications
- Low stock warnings

### Sync Inventory
```bash
php artisan merchandise:sync-inventory [--location=] [--dry-run]
```
- Reconcile physical counts
- Generate adjustment reports

## Notifications

### MerchandiseApplicationReceivedNotification
- Sent to admins when band applies
- Review queue link

### MerchandiseApplicationApprovedNotification
- Sent to band when approved
- Onboarding instructions

### MerchandisePayoutReadyNotification
- Sent to band monthly
- Payout summary
- Payment timeline

### MerchandiseLowStockNotification
- Sent to band when inventory low
- Restock request form

### MerchandiseSaleNotification (optional)
- Real-time sale notifications
- Daily/weekly digest option

## Widgets

### MerchandiseSalesSummaryWidget
- Today's sales
- This week/month
- Top products

### InventoryAlertsWidget
- Low stock items
- Out of stock
- Reorder needed

### PendingPayoutsWidget
- Payouts awaiting processing
- Total amount
- Quick process action

## Integration Points

### Productions
- Event merchandise sales
- Band product highlighting
- Post-show merchandise links

### Transactions
- All sales create Transaction records
- Unified financial reporting
- Tax documentation

### Member Profiles
- Purchase history
- Saved payment methods
- Member discount automatic

### Bands
- Link merchandise to band profiles
- Show merchandise on band pages
- Cross-promote shows and products

## Permissions

### Roles
- `merchandise_manager` - Full management access
- `merchandise_seller` - POS access, sales processing
- `band_member` - Access to own band's merchandise portal (if band is active in program)

### Abilities
- `manage_merchandise` - Full admin
- `process_merchandise_sales` - POS operations
- `process_payouts` - Financial operations
- `view_merchandise_reports` - Analytics access
- `manage_own_band_merchandise` - Band self-service (requires band membership verification)

### Access Control
- **Applications**: Only band members can submit applications for their bands
- **Band Portal**: Access requires active membership in the band (checked via `band_members` relationship)
- **Approval**: Contact user must be verified as band member before application approval
- **Eligibility**: Only CMC bands (in the Band directory) can participate in the program
- **Multi-band Members**: Users in multiple bands see a band selector in the merchandise portal

## Implementation Estimates

### Phase 1: Core Models & Inventory (18-24 hours)
- Database migrations
- Models and relationships
- Basic MerchandiseService
- Inventory management

### Phase 2: Sales Processing (16-20 hours)
- Sale creation workflow
- Revenue split calculations
- Transaction integration
- Refund handling

### Phase 3: Consignment & Payouts (14-18 hours)
- Application system
- Payout generation
- Stripe Connect integration
- Band notifications

### Phase 4: Filament Resources (20-26 hours)
- All admin resources
- Band portal pages
- Inventory management UI
- Sales reporting

### Phase 5: POS Interface (16-22 hours)
- Mobile POS for events
- Fixed POS for retail
- Stripe Terminal integration
- Receipt generation

### Phase 6: Online Store (18-24 hours)
- Public product catalog
- Shopping cart
- Checkout flow
- Member discount integration

### Phase 7: Analytics & Reporting (10-14 hours)
- Sales dashboards
- Band reports
- Inventory analytics
- Export functionality

### Phase 8: Testing & Polish (10-14 hours)
- Feature tests
- POS testing
- End-to-end workflows
- Documentation

**Total Estimate: 122-162 hours**

## Future Enhancements

### Advanced Features
- Print-on-demand integration (Printful, Printify)
- Digital downloads (music, art)
- Pre-orders for new releases
- Gift wrapping and cards
- Loyalty program integration
- Bundle deals and promotions
- Cross-sell recommendations
- Customer wishlists

### POS Enhancements
- Offline mode with sync
- Multiple terminal support
- Employee performance tracking
- Shift management
- Cash drawer reconciliation
- Barcode scanner integration

### Band Features
- Design templates for merch
- Bulk upload tools
- Sales forecasting
- Inventory optimization suggestions
- Promotional campaign tools
- Fan club integration

### Financial
- Multi-currency support
- International shipping
- Wholesale portal for other venues
- Consignment contracts automation
- Tax form generation (1099)
- Accounting software integration

### Marketing
- Email marketing integration
- Social media product feeds
- Influencer affiliate program
- Flash sales and limited editions
- Abandoned cart recovery
- Product review system
