# Poster Printing & Distribution System Design

## Overview

A comprehensive poster design, printing, and distribution service for CMC music events. Combines free bi-weekly community event listing posters with affordable individual poster printing services. Leverages CMC's printing equipment and volunteer network to provide professional promotional materials at accessible prices while generating sustainable revenue.

## Goals

- **Community Listings**: Bi-weekly posters featuring multiple local events
- **Individual Posters**: Affordable custom poster printing and distribution
- **Template Library**: Designer-created templates with artist royalties
- **Distribution Network**: Coordinated street team and location management
- **Sponsor Integration**: Local business sponsorships for listing posters
- **Digital Integration**: QR codes linking to event details and tickets
- **Self-Service**: Member portal for design and ordering
- **Revenue Generation**: Sustainable pricing model

## Core Features

### 1. Community Event Listing Posters

- Bi-weekly publication schedule
- 8-12 featured events per poster
- QR codes to digital calendar
- Sponsor logo placement
- 50-75 distribution locations
- Free event submissions
- Designed by CMC staff or volunteers

### 2. Individual Poster Service

**DIY Option (Free)**
- Template library access
- Self-service customization
- Print file download
- No distribution

**Basic Print** ($20 members / $30 non-members)
- Template customization
- Professional printing (25-100 copies)
- Standard paper/finish
- No distribution

**Full Service** ($40 members / $60 non-members)
- Everything in Basic
- City-wide distribution via street team
- 50-75 locations
- Distribution tracking

**Sustaining Member Discount**: 50% off all printing services

### 3. Template Library

- Professionally designed templates
- Artist attribution and royalties
- Genre/style categorization
- Customizable elements
- Preview before printing
- Download print-ready files

### 4. Distribution Network

- Location database (venues, shops, cafes)
- Street team coordination
- Distribution routes
- Placement tracking
- Restocking schedules
- Location analytics

### 5. Design Tools

- Web-based template editor
- Font and color customization
- Image upload
- QR code generation
- Print preview
- Export to PDF/PNG

## Database Schema

### Tables

#### `poster_templates`
```
id - bigint primary key
name - string
description - text
category - enum (show, festival, workshop, general)
style_tags - json (psychedelic, minimalist, vintage, etc.)
artist_id - foreign key to users (designer)
preview_image_url - string
template_file_path - string (design file)
customizable_fields - json (which elements can be edited)
royalty_percentage - integer (artist's cut per use)
usage_count - integer (default 0)
is_active - boolean (default true)
is_featured - boolean (default false)
price_tier - enum (free, basic, premium)
created_at, updated_at
```

#### `poster_orders`
```
id - bigint primary key
user_id - foreign key to users (who ordered)
event_id - foreign key nullable (Production, CommunityEvent, ProgramSession)
event_type - string nullable
order_type - enum (community_listing, individual_diy, individual_basic, individual_full)
poster_template_id - foreign key nullable
status - enum (draft, submitted, approved, printing, printed, distributed, completed, cancelled)
quantity - integer
paper_size - enum (11x17, 18x24, 24x36)
paper_type - enum (standard, glossy, matte, cardstock)
finish - enum (none, laminate, uv_coating)
design_data - json (customized template data)
proof_url - string nullable
distribution_requested - boolean (default false)
subtotal - integer (cents)
discount_amount - integer (cents)
total - integer (cents)
transaction_id - foreign key to transactions nullable
notes - text nullable
approved_by - foreign key to users nullable
approved_at - timestamp nullable
printed_at - timestamp nullable
distributed_at - timestamp nullable
created_at, updated_at, deleted_at
```

#### `community_listing_posters`
```
id - bigint primary key
title - string (e.g., "Oct 15-31 Music Events")
publication_date - date
period_start - date
period_end - date
status - enum (draft, published, distributed, archived)
design_notes - text nullable
proof_url - string nullable
final_file_url - string nullable
print_quantity - integer
designed_by - foreign key to users nullable
approved_by - foreign key to users nullable
approved_at - timestamp nullable
printed_at - timestamp nullable
created_at, updated_at
```

#### `community_listing_events`
```
id - bigint primary key
community_listing_poster_id - foreign key
event_id - bigint
event_type - string (Production, CommunityEvent, etc.)
position - integer (order on poster)
qr_code_url - string (generated QR for this event)
created_at, updated_at
```

#### `poster_sponsors`
```
id - bigint primary key
name - string
logo_url - string
website_url - string nullable
contact_name - string
contact_email - string
contact_phone - string nullable
sponsorship_tier - enum (bronze, silver, gold)
monthly_rate - integer (cents)
is_active - boolean (default true)
notes - text nullable
created_at, updated_at, deleted_at
```

#### `community_listing_sponsorships`
```
id - bigint primary key
community_listing_poster_id - foreign key
poster_sponsor_id - foreign key
placement - enum (top, bottom, sidebar)
amount_paid - integer (cents)
invoice_sent_at - timestamp nullable
paid_at - timestamp nullable
created_at, updated_at
```

#### `distribution_locations`
```
id - bigint primary key
name - string
location_type - enum (venue, coffee_shop, record_store, community_board, library, campus, other)
address - string
city - string (default 'Corvallis')
postal_code - string nullable
contact_name - string nullable
contact_email - string nullable
contact_phone - string nullable
max_posters - integer (how many can be posted)
notes - text nullable (placement instructions)
latitude - decimal nullable
longitude - decimal nullable
is_active - boolean (default true)
created_at, updated_at, deleted_at
```

#### `distribution_routes`
```
id - bigint primary key
name - string (e.g., "Downtown Route", "Campus Route")
description - text nullable
estimated_duration_minutes - integer
is_active - boolean (default true)
created_at, updated_at
```

#### `distribution_route_locations`
```
id - bigint primary key
distribution_route_id - foreign key
distribution_location_id - foreign key
stop_order - integer
created_at, updated_at
```

#### `distribution_runs`
```
id - bigint primary key
poster_order_id - foreign key nullable (individual poster)
community_listing_poster_id - foreign key nullable (listing poster)
distribution_route_id - foreign key
assigned_to - foreign key to users (street team member)
scheduled_date - date
status - enum (scheduled, in_progress, completed, cancelled)
started_at - timestamp nullable
completed_at - timestamp nullable
notes - text nullable
created_at, updated_at
```

#### `distribution_placements`
```
id - bigint primary key
distribution_run_id - foreign key
distribution_location_id - foreign key
quantity_placed - integer
photo_url - string nullable (proof of placement)
notes - text nullable
placed_at - timestamp
created_at
```

#### `poster_analytics`
```
id - bigint primary key
poster_order_id - foreign key nullable
community_listing_poster_id - foreign key nullable
metric_type - enum (qr_scan, website_visit, ticket_click, location_view)
metric_value - integer (default 1)
date - date
metadata - json (location, device, etc.)
created_at
```

#### `template_purchases`
```
id - bigint primary key
user_id - foreign key to users
poster_template_id - foreign key
poster_order_id - foreign key nullable
amount - integer (cents, if premium template)
royalty_amount - integer (cents, paid to artist)
created_at
```

## Models & Relationships

### PosterTemplate
```php
class PosterTemplate extends Model
{
    use InteractsWithMedia;

    protected $casts = [
        'style_tags' => 'array',
        'customizable_fields' => 'array',
        'royalty_percentage' => 'integer',
        'usage_count' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function artist()
    {
        return $this->belongsTo(User::class, 'artist_id');
    }

    public function orders()
    {
        return $this->hasMany(PosterOrder::class);
    }

    public function purchases()
    {
        return $this->hasMany(TemplatePurchase::class);
    }

    /**
     * Get preview image
     */
    public function getPreviewImageAttribute()
    {
        return $this->getFirstMediaUrl('previews') ?: $this->preview_image_url;
    }

    /**
     * Check if template is free
     */
    public function isFree(): bool
    {
        return $this->price_tier === 'free';
    }

    /**
     * Get template price
     */
    public function getPriceAttribute(): int
    {
        return match($this->price_tier) {
            'free' => 0,
            'basic' => 500, // $5
            'premium' => 1000, // $10
        };
    }

    /**
     * Calculate artist royalty
     */
    public function calculateRoyalty(int $salePrice): int
    {
        if (!$this->royalty_percentage) {
            return 0;
        }
        return (int)($salePrice * ($this->royalty_percentage / 100));
    }

    /**
     * Increment usage count
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
```

### PosterOrder
```php
class PosterOrder extends Model
{
    use SoftDeletes, LogsActivity;

    protected $casts = [
        'quantity' => 'integer',
        'design_data' => 'array',
        'distribution_requested' => 'boolean',
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'total' => 'integer',
        'approved_at' => 'datetime',
        'printed_at' => 'datetime',
        'distributed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->morphTo('event');
    }

    public function template()
    {
        return $this->belongsTo(PosterTemplate::class, 'poster_template_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function distributionRuns()
    {
        return $this->hasMany(DistributionRun::class);
    }

    public function analytics()
    {
        return $this->hasMany(PosterAnalytic::class);
    }

    /**
     * Check if order can be edited
     */
    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'submitted']);
    }

    /**
     * Check if order needs approval
     */
    public function needsApproval(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Approve order
     */
    public function approve(User $approver): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark as printed
     */
    public function markPrinted(): void
    {
        $this->update([
            'status' => 'printed',
            'printed_at' => now(),
        ]);
    }

    /**
     * Get proof URL
     */
    public function getProofUrlAttribute($value)
    {
        return $value ?: asset('storage/poster-proofs/' . $this->id . '.pdf');
    }
}
```

### CommunityListingPoster
```php
class CommunityListingPoster extends Model
{
    protected $casts = [
        'publication_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'print_quantity' => 'integer',
        'approved_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    public function events()
    {
        return $this->hasMany(CommunityListingEvent::class);
    }

    public function sponsorships()
    {
        return $this->hasMany(CommunityListingSponsorship::class);
    }

    public function designer()
    {
        return $this->belongsTo(User::class, 'designed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function distributionRuns()
    {
        return $this->hasMany(DistributionRun::class);
    }

    /**
     * Get sponsor revenue
     */
    public function getSponsorRevenueAttribute(): int
    {
        return $this->sponsorships()->sum('amount_paid');
    }

    /**
     * Get title display
     */
    public function getTitleDisplayAttribute(): string
    {
        return $this->title ?: "Community Events {$this->period_start->format('M j')} - {$this->period_end->format('M j')}";
    }
}
```

### CommunityListingEvent
```php
class CommunityListingEvent extends Model
{
    protected $casts = [
        'position' => 'integer',
    ];

    public function poster()
    {
        return $this->belongsTo(CommunityListingPoster::class, 'community_listing_poster_id');
    }

    public function event()
    {
        return $this->morphTo();
    }
}
```

### PosterSponsor
```php
class PosterSponsor extends Model
{
    use SoftDeletes;

    protected $casts = [
        'monthly_rate' => 'integer',
        'is_active' => 'boolean',
    ];

    public function sponsorships()
    {
        return $this->hasMany(CommunityListingSponsorship::class);
    }

    /**
     * Get total sponsorship amount
     */
    public function getTotalSponsorshipAttribute(): int
    {
        return $this->sponsorships()
            ->whereNotNull('paid_at')
            ->sum('amount_paid');
    }
}
```

### DistributionLocation
```php
class DistributionLocation extends Model
{
    use SoftDeletes;

    protected $casts = [
        'max_posters' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function routes()
    {
        return $this->belongsToMany(
            DistributionRoute::class,
            'distribution_route_locations'
        )
            ->withPivot('stop_order')
            ->orderByPivot('stop_order');
    }

    public function placements()
    {
        return $this->hasMany(DistributionPlacement::class);
    }

    /**
     * Get recent placement count
     */
    public function getRecentPlacementsCount(int $days = 30): int
    {
        return $this->placements()
            ->where('placed_at', '>=', now()->subDays($days))
            ->sum('quantity_placed');
    }

    /**
     * Scope for active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

### DistributionRoute
```php
class DistributionRoute extends Model
{
    protected $casts = [
        'estimated_duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function locations()
    {
        return $this->belongsToMany(
            DistributionLocation::class,
            'distribution_route_locations'
        )
            ->withPivot('stop_order')
            ->orderByPivot('stop_order');
    }

    public function runs()
    {
        return $this->hasMany(DistributionRun::class);
    }

    /**
     * Get location count
     */
    public function getLocationCountAttribute(): int
    {
        return $this->locations()->count();
    }
}
```

### DistributionRun
```php
class DistributionRun extends Model
{
    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function posterOrder()
    {
        return $this->belongsTo(PosterOrder::class);
    }

    public function communityListingPoster()
    {
        return $this->belongsTo(CommunityListingPoster::class);
    }

    public function route()
    {
        return $this->belongsTo(DistributionRoute::class);
    }

    public function assignedMember()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function placements()
    {
        return $this->hasMany(DistributionPlacement::class);
    }

    /**
     * Start distribution run
     */
    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Complete distribution run
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentageAttribute(): float
    {
        $totalLocations = $this->route->locations()->count();
        $completed = $this->placements()->count();

        if ($totalLocations === 0) {
            return 0;
        }

        return ($completed / $totalLocations) * 100;
    }
}
```

### DistributionPlacement
```php
class DistributionPlacement extends Model
{
    public $timestamps = false;

    protected $casts = [
        'quantity_placed' => 'integer',
        'placed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(DistributionRun::class, 'distribution_run_id');
    }

    public function location()
    {
        return $this->belongsTo(DistributionLocation::class);
    }
}
```

## Service Layer

### PosterService

```php
class PosterService
{
    /**
     * Create poster order
     */
    public function createOrder(
        User $user,
        array $data,
        ?Model $event = null
    ): PosterOrder {
        $template = PosterTemplate::findOrFail($data['template_id']);
        $orderType = $data['order_type'];

        // Calculate pricing
        $pricing = $this->calculatePricing($user, $template, $orderType, $data['quantity'] ?? 100);

        $order = PosterOrder::create([
            'user_id' => $user->id,
            'event_id' => $event?->id,
            'event_type' => $event ? get_class($event) : null,
            'order_type' => $orderType,
            'poster_template_id' => $template->id,
            'status' => 'draft',
            'quantity' => $data['quantity'] ?? 100,
            'paper_size' => $data['paper_size'] ?? '11x17',
            'paper_type' => $data['paper_type'] ?? 'standard',
            'finish' => $data['finish'] ?? 'none',
            'design_data' => $data['design_data'] ?? [],
            'distribution_requested' => $data['distribution_requested'] ?? false,
            'subtotal' => $pricing['subtotal'],
            'discount_amount' => $pricing['discount'],
            'total' => $pricing['total'],
        ]);

        // Record template usage
        $template->recordUsage();

        return $order;
    }

    /**
     * Calculate order pricing
     */
    protected function calculatePricing(
        User $user,
        PosterTemplate $template,
        string $orderType,
        int $quantity
    ): array {
        $subtotal = 0;

        // Base pricing by order type
        $basePrices = [
            'individual_diy' => 0,
            'individual_basic' => 2000, // $20
            'individual_full' => 4000, // $40
        ];

        $subtotal = $basePrices[$orderType] ?? 0;

        // Template fee (if premium)
        if (!$template->isFree()) {
            $subtotal += $template->price;
        }

        // Calculate discount
        $discount = 0;

        // Sustaining member discount (50%)
        if ($user->isSustainingMember()) {
            $discount = (int)($subtotal * 0.5);
        }

        $total = $subtotal - $discount;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
        ];
    }

    /**
     * Submit order for approval
     */
    public function submitOrder(PosterOrder $order): void
    {
        if (!$order->canEdit()) {
            throw new Exception('Order cannot be submitted');
        }

        $order->update(['status' => 'submitted']);

        // Create transaction if payment required
        if ($order->total > 0) {
            Transaction::create([
                'user_id' => $order->user_id,
                'amount' => $order->total,
                'type' => 'poster_order',
                'status' => 'pending',
                'description' => "Poster printing: {$order->order_type}",
            ]);
        }

        // Notify admins
        $this->notifyAdminsOfNewOrder($order);
    }

    /**
     * Generate proof PDF
     */
    public function generateProof(PosterOrder $order): string
    {
        // Merge template with design data
        $pdf = PDF::loadView('posters.proof', [
            'order' => $order,
            'template' => $order->template,
            'design_data' => $order->design_data,
        ]);

        $filename = "poster-proof-{$order->id}.pdf";
        $path = storage_path('app/public/poster-proofs/' . $filename);

        $pdf->save($path);

        return $path;
    }

    /**
     * Generate QR code for event
     */
    public function generateQrCode(Model $event): string
    {
        $url = $this->getEventUrl($event);

        $qrCode = QrCode::size(200)
            ->format('png')
            ->generate($url);

        $filename = "qr-{$event->id}-" . Str::random(8) . ".png";
        $path = storage_path('app/public/qr-codes/' . $filename);

        file_put_contents($path, $qrCode);

        return asset('storage/qr-codes/' . $filename);
    }

    /**
     * Create community listing poster
     */
    public function createCommunityListing(
        Carbon $periodStart,
        Carbon $periodEnd,
        array $eventIds = []
    ): CommunityListingPoster {
        $poster = CommunityListingPoster::create([
            'title' => "Community Events {$periodStart->format('M j')} - {$periodEnd->format('M j')}",
            'publication_date' => now(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
            'print_quantity' => 100,
        ]);

        // Add events
        foreach ($eventIds as $index => $eventData) {
            $event = $this->findEvent($eventData['type'], $eventData['id']);
            $qrUrl = $this->generateQrCode($event);

            CommunityListingEvent::create([
                'community_listing_poster_id' => $poster->id,
                'event_id' => $event->id,
                'event_type' => get_class($event),
                'position' => $index + 1,
                'qr_code_url' => $qrUrl,
            ]);
        }

        return $poster;
    }

    /**
     * Schedule distribution run
     */
    public function scheduleDistribution(
        $poster, // PosterOrder or CommunityListingPoster
        DistributionRoute $route,
        User $assignedTo,
        Carbon $scheduledDate
    ): DistributionRun {
        $data = [
            'distribution_route_id' => $route->id,
            'assigned_to' => $assignedTo->id,
            'scheduled_date' => $scheduledDate,
            'status' => 'scheduled',
        ];

        if ($poster instanceof PosterOrder) {
            $data['poster_order_id'] = $poster->id;
        } else {
            $data['community_listing_poster_id'] = $poster->id;
        }

        $run = DistributionRun::create($data);

        // Notify assigned member
        $assignedTo->notify(new DistributionRunAssignedNotification($run));

        return $run;
    }

    /**
     * Record placement
     */
    public function recordPlacement(
        DistributionRun $run,
        DistributionLocation $location,
        int $quantity,
        ?string $photoUrl = null,
        ?string $notes = null
    ): DistributionPlacement {
        return DistributionPlacement::create([
            'distribution_run_id' => $run->id,
            'distribution_location_id' => $location->id,
            'quantity_placed' => $quantity,
            'photo_url' => $photoUrl,
            'notes' => $notes,
            'placed_at' => now(),
        ]);
    }

    /**
     * Track QR scan
     */
    public function trackQrScan($poster, array $metadata = []): void
    {
        $data = [
            'metric_type' => 'qr_scan',
            'metric_value' => 1,
            'date' => now()->toDateString(),
            'metadata' => $metadata,
        ];

        if ($poster instanceof PosterOrder) {
            $data['poster_order_id'] = $poster->id;
        } else {
            $data['community_listing_poster_id'] = $poster->id;
        }

        PosterAnalytic::create($data);
    }

    /**
     * Get location recommendations
     */
    public function getLocationRecommendations(Model $event): Collection
    {
        // Find locations based on event type, venue, genre, etc.
        $recommendations = DistributionLocation::active()->get();

        // TODO: Implement smart recommendations based on:
        // - Event genre/type
        // - Venue proximity
        // - Past successful placements
        // - Location traffic patterns

        return $recommendations;
    }

    /**
     * Calculate artist royalty
     */
    public function calculateArtistRoyalty(PosterOrder $order): int
    {
        if (!$order->template || !$order->template->artist_id) {
            return 0;
        }

        return $order->template->calculateRoyalty($order->total);
    }

    /**
     * Process template purchase
     */
    public function purchaseTemplate(
        User $user,
        PosterTemplate $template,
        PosterOrder $order
    ): TemplatePurchase {
        $royalty = $template->calculateRoyalty($template->price);

        return TemplatePurchase::create([
            'user_id' => $user->id,
            'poster_template_id' => $template->id,
            'poster_order_id' => $order->id,
            'amount' => $template->price,
            'royalty_amount' => $royalty,
        ]);
    }

    /**
     * Get event URL
     */
    protected function getEventUrl(Model $event): string
    {
        if ($event instanceof Production) {
            return route('productions.show', $event->slug);
        } elseif ($event instanceof CommunityEvent) {
            return route('community-events.show', $event->id);
        } elseif ($event instanceof ProgramSession) {
            return route('programs.sessions.show', [$event->program->slug, $event->id]);
        }

        return route('calendar');
    }

    /**
     * Find event by type and ID
     */
    protected function findEvent(string $type, int $id): Model
    {
        return match($type) {
            'Production' => Production::findOrFail($id),
            'CommunityEvent' => CommunityEvent::findOrFail($id),
            'ProgramSession' => ProgramSession::findOrFail($id),
            default => throw new Exception("Unknown event type: {$type}"),
        };
    }
}
```

## Filament Resources

### PosterOrderResource
- Location: `/member/posters/orders`
- Create new poster orders
- Template selection and customization
- Upload custom images
- Preview before submission
- Order status tracking
- Download proofs and final files

### CommunityListingPosterResource
- Location: `/member/posters/community-listings`
- Admin only
- Create bi-weekly listings
- Select featured events
- Add sponsors
- Generate QR codes
- Schedule distribution

### PosterTemplateResource
- Location: `/member/posters/templates`
- Browse template library
- Filter by category/style
- Preview templates
- Admin: Upload new templates
- Artist: View royalty earnings

### DistributionLocationResource
- Location: `/member/posters/locations`
- Location directory with map
- Add/edit locations
- Track placement history
- Location performance metrics

### DistributionRunResource
- Location: `/member/posters/distribution`
- Schedule distribution runs
- Assign to street team
- Track progress
- View placement photos
- Route optimization

### PosterSponsorResource
- Location: `/member/posters/sponsors`
- Admin only
- Sponsor directory
- Invoice generation
- Payment tracking
- Sponsorship history

## Mobile App (Street Team)

### Distribution App Features
- View assigned routes
- Turn-by-turn navigation
- Location check-in
- Photo upload for proof
- Quantity tracking
- Notes per location
- Offline mode with sync

## Public Features

### QR Code Landing Pages
- Event details from QR scan
- Ticket links
- Venue info and map
- Social sharing
- Add to calendar
- Track referral source

### Community Calendar
- All events from listing posters
- Filter by date, genre, venue
- Map view
- Subscribe to updates
- Submit events for inclusion

## Commands

### Generate Bi-weekly Listings
```bash
php artisan posters:generate-community-listing [--period-start=] [--period-end=]
```
- Auto-select upcoming events
- Generate QR codes
- Create poster draft
- Notify admin for review

### Schedule Distribution Runs
```bash
php artisan posters:schedule-distribution [--date=]
```
- Create runs for pending posters
- Assign to street team
- Send notifications

### Calculate Artist Royalties
```bash
php artisan posters:calculate-royalties [--month=]
```
- Sum template usage
- Calculate royalties owed
- Generate payout records

### Sync Location Data
```bash
php artisan posters:sync-locations
```
- Geocode addresses
- Update coordinates
- Validate location data

## Notifications

### PosterOrderSubmittedNotification
- Sent to admins when order submitted
- Quick approval link

### PosterOrderApprovedNotification
- Sent to customer when approved
- Estimated completion date

### PosterPrintedNotification
- Sent when printing complete
- Pickup/distribution info

### DistributionRunAssignedNotification
- Sent to street team member
- Route details and instructions

### DistributionRunCompletedNotification
- Sent to admin when run complete
- Summary stats

### SponsorInvoiceNotification
- Sent to sponsors monthly
- Invoice with poster placements

### TemplateRoyaltyNotification
- Sent to artists monthly
- Royalty earnings summary

## Widgets

### PosterOrdersQueueWidget
- Pending orders
- Needs approval count
- Ready to print

### DistributionScheduleWidget
- Upcoming runs
- Assigned team members
- Route summaries

### SponsorRevenueWidget
- Monthly sponsor income
- Pending invoices
- Active sponsors

### TemplatePerformanceWidget
- Most used templates
- Artist earnings
- Popular categories

## Integration Points

### Productions
- Create poster directly from production
- Auto-populate event details
- Link to ticket sales
- Track promotional effectiveness

### Community Events
- Include in bi-weekly listings
- QR code landing pages
- Calendar integration

### Volunteer System
- Street team volunteer roles
- Distribution run assignments
- Hour tracking for distribution

### Transactions
- Payment for poster orders
- Sponsor invoice tracking
- Artist royalty payouts

## Permissions

### Roles
- `poster_admin` - Full poster management
- `street_team` - Distribution app access
- `designer` - Upload templates, view royalties

### Abilities
- `create_poster_order` - All members
- `manage_posters` - Admins
- `manage_distribution` - Admins, team leads
- `upload_templates` - Designers
- `view_analytics` - Admins

## Pricing Summary

### Individual Posters

**DIY (Download Only)**: FREE
- Template access
- Self-service design
- Print file download

**Basic Print**: $20 members / $30 non-members
- Template customization
- Professional printing (25-100 copies)
- Pickup at CMC

**Full Service**: $40 members / $60 non-members
- Everything in Basic
- City-wide distribution
- 50-75 locations

**Sustaining Member Discount**: 50% off all services

### Community Listings
- FREE event inclusion
- Sponsored by local businesses
- Bi-weekly publication
- City-wide distribution

### Template Pricing
- **Free**: Community templates
- **Basic**: $5 (designer gets $2.50)
- **Premium**: $10 (designer gets $5)

## Revenue Model

### Income Streams
- Poster printing fees
- Distribution service fees
- Premium template sales
- Community listing sponsorships

### Expenses
- Printing costs (paper, ink, maintenance)
- Distribution labor (street team)
- Template designer royalties
- Storage and hosting

### Sustainability
- Equipment already owned (sunk cost)
- Volunteer street team reduces labor
- Sponsorships cover listing posters
- Member discounts incentivize upgrades

## Implementation Estimates

### Phase 1: Core Order System (16-20 hours)
- Database migrations
- Models and relationships
- PosterService basics
- Order creation workflow

### Phase 2: Template System (14-18 hours)
- Template library
- Upload and management
- Customization interface
- Preview generation
- Royalty tracking

### Phase 3: Community Listings (12-16 hours)
- Listing poster creation
- Event selection
- QR code generation
- Sponsor integration

### Phase 4: Distribution System (18-24 hours)
- Location database
- Route management
- Run scheduling
- Placement tracking

### Phase 5: Filament Resources (20-26 hours)
- All admin resources
- Order management UI
- Distribution interfaces
- Analytics dashboards

### Phase 6: Mobile Distribution App (24-32 hours)
- Route navigation
- Photo upload
- Check-in system
- Offline sync

### Phase 7: QR Landing Pages (10-14 hours)
- Dynamic event pages
- QR scan tracking
- Social sharing
- Analytics

### Phase 8: Design Tools (16-22 hours)
- Web-based editor
- Template customization
- Image upload
- Export to print formats

### Phase 9: Analytics & Reporting (10-14 hours)
- Placement metrics
- QR scan tracking
- Sponsor ROI reports
- Artist royalty reports

### Phase 10: Testing & Polish (10-14 hours)
- Feature tests
- Print quality tests
- Distribution workflow tests
- Documentation

**Total Estimate: 150-200 hours**

## Future Enhancements

### Advanced Features
- AI-powered design suggestions
- Automated A/B testing for designs
- Video poster animations (digital displays)
- Augmented reality poster experiences
- Integration with digital billboards
- Dynamic QR codes (update destination)
- Weather-based distribution optimization

### Template Marketplace
- Public template submission
- Rating and review system
- Featured designer spotlights
- Template bundles and packages
- Seasonal template collections
- Custom commission requests

### Distribution Optimization
- Machine learning for location recommendations
- Heat maps of poster effectiveness
- Automated route optimization
- Predictive restocking alerts
- Photo recognition for placement verification
- Vandalism/removal tracking

### Analytics Enhancements
- Conversion tracking (view → ticket purchase)
- Demographic data from QR scans
- Location performance scoring
- A/B testing framework
- ROI calculator for sponsors
- Competitive analysis tools

### Community Features
- Poster hall of fame
- Community voting on designs
- Collaborative design tools
- Poster swap events
- Archive of historical posters
- Virtual gallery exhibitions
