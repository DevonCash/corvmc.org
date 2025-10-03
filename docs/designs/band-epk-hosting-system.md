# Extended Band Profile & EPK Hosting System Design

## Overview

A comprehensive band profile and Electronic Press Kit (EPK) hosting service that enhances the basic CMC member directory with premium customizable profiles. Provides bands with professional online presence tools, multimedia hosting, custom styling, and industry-standard EPK features at affordable subscription pricing. Only available to CMC member bands.

## Goals

- **Tiered Profiles**: Free basic listings → Premium enhanced profiles
- **EPK Features**: Press photos, bios, tour dates, streaming audio, contact forms
- **Customization**: Custom CSS, template library, subdomain hosting
- **Self-Service**: Band members manage their own content
- **Analytics**: Track views, engagement, booking inquiries
- **Affordability**: $15-25/month, cheaper than custom websites
- **Integration**: Connect with existing Band profiles, productions, merchandise

## Core Features

### 1. Profile Tiers

#### Free Tier (Basic Directory Listing)
- Standard CMC member directory entry
- Band name, genre tags, formation year
- Basic contact info and social links
- Member list
- Thumbnail photo
- URL: `/bands/{slug}`

#### Premium Tier ($15-25/month)
- Custom subdomain: `bandname.corvmc.org`
- Multimedia hosting (images, audio, video)
- Custom CSS styling or templates
- Press kit sections (bio, photos, tour dates, press)
- Analytics dashboard
- Booking inquiry forms
- Additional storage (configurable limits)

### 2. Content Management

#### Media Hosting
- High-resolution press photos with captions
- Audio streaming (embedded players for demos/tracks)
- Video embedding (YouTube, Vimeo)
- Document uploads (tech riders, stage plots)
- Storage quotas by tier

#### Content Sections
- Multiple bio variants (50-word, 150-word, 500-word)
- Discography with streaming links
- Tour date calendar
- Press coverage and reviews
- Awards and accolades
- Contact information and booking details
- Social media aggregation

### 3. Customization

#### Visual Customization
- Custom CSS editor with live preview
- Template library (pre-designed themes)
- Color scheme picker
- Google Fonts integration
- Layout options (single-page, multi-section, etc.)
- Background images/patterns

#### Domain & Branding
- Subdomain hosting: `bandname.corvmc.org`
- Custom OG tags for social sharing
- Favicon upload
- Meta description customization

### 4. EPK Tools

#### Press Kit Generation
- One-click PDF export for booking agents
- Downloadable press photo package (ZIP)
- Embed code for press sections
- Print-friendly layouts
- Shareable EPK URL with access tracking

#### Booking Features
- Contact form with custom fields
- Tour date availability calendar
- Tech rider uploads
- Performance history
- Venue requirements and preferences

### 5. Analytics & Insights

- Profile view counts
- EPK download tracking
- Inquiry form submissions
- Popular content sections
- Referral sources
- Geographic audience data

### 6. Discovery & Promotion

- Enhanced search visibility in directory
- Featured profile rotation
- Genre/tag-based discovery
- Cross-promotion with other bands
- Integration with CMC events
- Social sharing optimization

## Database Schema

### Tables

#### `band_profile_tiers`
```
id - bigint primary key
name - string (free, premium, custom)
slug - string unique
monthly_price - integer (cents)
annual_price - integer (cents)
storage_limit_mb - integer
features - json (feature flags)
is_active - boolean
sort_order - integer
created_at, updated_at
```

#### `band_profile_subscriptions`
```
id - bigint primary key
band_id - foreign key to bands
band_profile_tier_id - foreign key
stripe_subscription_id - string nullable
status - enum (active, cancelled, expired, trial)
billing_cycle - enum (monthly, annual)
current_period_start - timestamp
current_period_end - timestamp
trial_ends_at - timestamp nullable
cancelled_at - timestamp nullable
storage_used_mb - integer (default 0)
subdomain - string unique nullable
settings - json
created_at, updated_at
```

#### `band_epk_sections`
```
id - bigint primary key
band_id - foreign key to bands
section_type - enum (bio, discography, press, tour_dates, media, contact, custom)
title - string
slug - string
content - longtext nullable
metadata - json (section-specific data)
position - integer
is_visible - boolean (default true)
created_at, updated_at
```

#### `band_bios`
```
id - bigint primary key
band_id - foreign key to bands
bio_type - enum (short_50, medium_150, long_500, full)
content - text
created_at, updated_at
```

#### `band_press_photos`
```
id - bigint primary key
band_id - foreign key to bands
title - string
caption - text nullable
photographer_name - string nullable
photographer_credit_url - string nullable
is_featured - boolean (default false)
download_count - integer (default 0)
position - integer
created_at, updated_at
```
*Uses Spatie MediaLibrary for actual file storage*

#### `band_discography_items`
```
id - bigint primary key
band_id - foreign key to bands
release_type - enum (album, ep, single, compilation, live)
title - string
release_date - date nullable
label - string nullable
description - text nullable
streaming_links - json (spotify, apple, bandcamp, etc.)
position - integer
created_at, updated_at
```
*Uses Spatie MediaLibrary for cover art*

#### `band_press_items`
```
id - bigint primary key
band_id - foreign key to bands
publication - string
article_title - string
article_url - string nullable
publish_date - date nullable
excerpt - text nullable
quote - text nullable (pull quote for EPK)
position - integer
created_at, updated_at
```

#### `band_tour_dates`
```
id - bigint primary key
band_id - foreign key to bands
production_id - foreign key to productions nullable (if CMC show)
date - date
venue_name - string
city - string
state - string nullable
country - string (default 'USA')
ticket_url - string nullable
status - enum (confirmed, tentative, cancelled)
is_past - boolean (computed)
created_at, updated_at
```

#### `band_customizations`
```
id - bigint primary key
band_id - foreign key to bands
template_id - foreign key to band_profile_templates nullable
custom_css - text nullable
custom_js - text nullable (sandboxed)
color_scheme - json (primary, secondary, background, text colors)
font_family - string nullable
layout_type - enum (single_page, multi_page, card_grid)
header_image_url - string nullable
favicon_url - string nullable
og_tags - json (social sharing metadata)
settings - json (misc customization flags)
created_at, updated_at
```

#### `band_profile_templates`
```
id - bigint primary key
name - string
description - text
preview_image_url - string nullable
css_content - text
layout_config - json
is_active - boolean (default true)
is_premium - boolean (default false)
created_at, updated_at
```

#### `band_epk_downloads`
```
id - bigint primary key
band_id - foreign key to bands
download_type - enum (pdf_epk, press_photos, full_package)
downloaded_by_email - string nullable
downloaded_by_ip - string
user_agent - string nullable
referrer - string nullable
created_at
```

#### `band_booking_inquiries`
```
id - bigint primary key
band_id - foreign key to bands
inquirer_name - string
inquirer_email - string
inquirer_phone - string nullable
venue_name - string nullable
event_date - date nullable
message - text
status - enum (new, replied, booked, declined, archived)
replied_at - timestamp nullable
replied_by - foreign key to users nullable
notes - text nullable (internal notes)
created_at, updated_at
```

#### `band_profile_analytics`
```
id - bigint primary key
band_id - foreign key to bands
metric_type - enum (profile_view, epk_download, inquiry_submission, media_play, link_click)
metric_value - integer (default 1)
date - date
metadata - json (referrer, section, etc.)
created_at
```

#### `band_tech_riders`
```
id - bigint primary key
band_id - foreign key to bands
title - string (e.g., "Standard Tech Rider", "Festival Rider")
description - text nullable
stage_plot_url - string nullable
is_default - boolean (default false)
created_at, updated_at
```
*Uses Spatie MediaLibrary for PDF uploads*

## Models & Relationships

### BandProfileTier
```php
class BandProfileTier extends Model
{
    protected $casts = [
        'monthly_price' => 'integer',
        'annual_price' => 'integer',
        'storage_limit_mb' => 'integer',
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public function subscriptions()
    {
        return $this->hasMany(BandProfileSubscription::class);
    }

    /**
     * Check if tier has feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Get monthly price in dollars
     */
    public function getMonthlyPriceDisplayAttribute(): string
    {
        return '$' . number_format($this->monthly_price / 100, 2);
    }
}
```

### BandProfileSubscription
```php
class BandProfileSubscription extends Model
{
    use LogsActivity;

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'storage_used_mb' => 'integer',
        'settings' => 'array',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    public function tier()
    {
        return $this->belongsTo(BandProfileTier::class, 'band_profile_tier_id');
    }

    /**
     * Get full subdomain URL
     */
    public function getSubdomainUrlAttribute(): ?string
    {
        if (!$this->subdomain) {
            return null;
        }
        return "https://{$this->subdomain}.corvmc.org";
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->current_period_end->isFuture();
    }

    /**
     * Check if on trial
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get storage usage percentage
     */
    public function getStorageUsagePercentageAttribute(): float
    {
        if (!$this->tier->storage_limit_mb) {
            return 0;
        }
        return ($this->storage_used_mb / $this->tier->storage_limit_mb) * 100;
    }

    /**
     * Check if storage limit reached
     */
    public function storageAvailable(int $additionalMb = 0): bool
    {
        $limit = $this->tier->storage_limit_mb;
        if (!$limit) {
            return true; // Unlimited
        }
        return ($this->storage_used_mb + $additionalMb) <= $limit;
    }
}
```

### BandEpkSection
```php
class BandEpkSection extends Model
{
    protected $casts = [
        'metadata' => 'array',
        'is_visible' => 'boolean',
        'position' => 'integer',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * Scope for visible sections
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true)->orderBy('position');
    }
}
```

### BandBio
```php
class BandBio extends Model
{
    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * Get word count
     */
    public function getWordCountAttribute(): int
    {
        return str_word_count(strip_tags($this->content));
    }
}
```

### BandPressPhoto
```php
class BandPressPhoto extends Model
{
    use InteractsWithMedia;

    protected $casts = [
        'is_featured' => 'boolean',
        'download_count' => 'integer',
        'position' => 'integer',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * Get photo URL
     */
    public function getPhotoUrlAttribute()
    {
        return $this->getFirstMediaUrl('press_photos', 'large');
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute()
    {
        return $this->getFirstMediaUrl('press_photos', 'thumb');
    }

    /**
     * Increment download count
     */
    public function recordDownload(): void
    {
        $this->increment('download_count');
    }
}
```

### BandDiscographyItem
```php
class BandDiscographyItem extends Model
{
    use InteractsWithMedia;

    protected $casts = [
        'release_date' => 'date',
        'streaming_links' => 'array',
        'position' => 'integer',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * Get cover art URL
     */
    public function getCoverArtUrlAttribute()
    {
        return $this->getFirstMediaUrl('cover_art');
    }

    /**
     * Scope for recent releases
     */
    public function scopeRecent($query, int $limit = 5)
    {
        return $query->orderByDesc('release_date')->limit($limit);
    }
}
```

### BandPressItem
```php
class BandPressItem extends Model
{
    protected $casts = [
        'publish_date' => 'date',
        'position' => 'integer',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * Scope for recent press
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('publish_date')->limit($limit);
    }
}
```

### BandTourDate
```php
class BandTourDate extends Model
{
    protected $casts = [
        'date' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function ($tourDate) {
            $tourDate->is_past = $tourDate->date->isPast();
        });
    }

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    /**
     * Scope for upcoming dates
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today())
            ->where('status', '!=', 'cancelled')
            ->orderBy('date');
    }

    /**
     * Scope for past dates
     */
    public function scopePast($query)
    {
        return $query->where('date', '<', today())
            ->orderByDesc('date');
    }
}
```

### BandCustomization
```php
class BandCustomization extends Model
{
    protected $casts = [
        'color_scheme' => 'array',
        'og_tags' => 'array',
        'settings' => 'array',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    public function template()
    {
        return $this->belongsTo(BandProfileTemplate::class, 'template_id');
    }

    /**
     * Get compiled CSS (template + custom)
     */
    public function getCompiledCssAttribute(): string
    {
        $css = '';

        // Template CSS
        if ($this->template) {
            $css .= $this->template->css_content . "\n\n";
        }

        // Color scheme variables
        if ($this->color_scheme) {
            $css .= ":root {\n";
            foreach ($this->color_scheme as $key => $value) {
                $css .= "  --{$key}: {$value};\n";
            }
            $css .= "}\n\n";
        }

        // Font family
        if ($this->font_family) {
            $css .= "body { font-family: '{$this->font_family}', sans-serif; }\n\n";
        }

        // Custom CSS
        if ($this->custom_css) {
            $css .= $this->custom_css;
        }

        return $css;
    }
}
```

### BandProfileTemplate
```php
class BandProfileTemplate extends Model
{
    protected $casts = [
        'layout_config' => 'array',
        'is_active' => 'boolean',
        'is_premium' => 'boolean',
    ];

    /**
     * Get preview image
     */
    public function getPreviewImageAttribute()
    {
        return $this->preview_image_url ?: '/images/template-placeholder.png';
    }
}
```

### BandBookingInquiry
```php
class BandBookingInquiry extends Model
{
    use LogsActivity;

    protected $casts = [
        'event_date' => 'date',
        'replied_at' => 'datetime',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }

    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * Mark as replied
     */
    public function markReplied(User $user): void
    {
        $this->update([
            'status' => 'replied',
            'replied_at' => now(),
            'replied_by' => $user->id,
        ]);
    }

    /**
     * Scope for new inquiries
     */
    public function scopeNew($query)
    {
        return $query->where('status', 'new')->orderBy('created_at');
    }
}
```

### BandProfileAnalytic
```php
class BandProfileAnalytic extends Model
{
    public $timestamps = false;

    protected $casts = [
        'date' => 'date',
        'metric_value' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function band()
    {
        return $this->belongsTo(Band::class);
    }
}
```

## Extend Existing Band Model

```php
// Add to app/Models/Band.php

public function profileSubscription()
{
    return $this->hasOne(BandProfileSubscription::class);
}

public function epkSections()
{
    return $this->hasMany(BandEpkSection::class);
}

public function bios()
{
    return $this->hasMany(BandBio::class);
}

public function pressPhotos()
{
    return $this->hasMany(BandPressPhoto::class)->orderBy('position');
}

public function discography()
{
    return $this->hasMany(BandDiscographyItem::class)->orderBy('position');
}

public function pressItems()
{
    return $this->hasMany(BandPressItem::class)->orderBy('position');
}

public function tourDates()
{
    return $this->hasMany(BandTourDate::class);
}

public function customization()
{
    return $this->hasOne(BandCustomization::class);
}

public function bookingInquiries()
{
    return $this->hasMany(BandBookingInquiry::class);
}

public function techRiders()
{
    return $this->hasMany(BandTechRider::class);
}

/**
 * Check if band has premium profile
 */
public function hasPremiumProfile(): bool
{
    return $this->profileSubscription &&
           $this->profileSubscription->isActive() &&
           $this->profileSubscription->tier->slug !== 'free';
}

/**
 * Get profile URL
 */
public function getProfileUrlAttribute(): string
{
    if ($this->hasPremiumProfile() && $this->profileSubscription->subdomain) {
        return $this->profileSubscription->subdomain_url;
    }
    return route('bands.show', $this->slug);
}
```

## Service Layer

### BandEpkService

```php
class BandEpkService
{
    /**
     * Create premium subscription
     */
    public function createSubscription(
        Band $band,
        BandProfileTier $tier,
        string $billingCycle = 'monthly',
        ?string $subdomain = null
    ): BandProfileSubscription {
        // Validate subdomain
        if ($subdomain && !$this->validateSubdomain($subdomain)) {
            throw new Exception('Invalid or unavailable subdomain');
        }

        // Create Stripe subscription
        $stripeSubscription = $this->createStripeSubscription($band, $tier, $billingCycle);

        return BandProfileSubscription::create([
            'band_id' => $band->id,
            'band_profile_tier_id' => $tier->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'subdomain' => $subdomain,
        ]);
    }

    /**
     * Generate EPK PDF
     */
    public function generateEpkPdf(Band $band): string
    {
        $pdf = PDF::loadView('band-epk.pdf', [
            'band' => $band->load([
                'bios',
                'pressPhotos',
                'discography',
                'pressItems',
                'tourDates',
                'techRiders',
            ]),
        ]);

        $filename = Str::slug($band->name) . '-epk-' . now()->format('Y-m-d') . '.pdf';
        $path = storage_path('app/epks/' . $filename);

        $pdf->save($path);

        // Track download
        $this->trackMetric($band, 'epk_download');

        return $path;
    }

    /**
     * Generate press photo package (ZIP)
     */
    public function generatePressPhotoPackage(Band $band): string
    {
        $zip = new ZipArchive();
        $filename = Str::slug($band->name) . '-press-photos-' . now()->format('Y-m-d') . '.zip';
        $path = storage_path('app/press-packages/' . $filename);

        if ($zip->open($path, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Could not create ZIP file');
        }

        foreach ($band->pressPhotos as $photo) {
            $mediaPath = $photo->getFirstMediaPath('press_photos');
            if ($mediaPath && file_exists($mediaPath)) {
                $zip->addFile($mediaPath, basename($mediaPath));
            }

            // Increment download count
            $photo->recordDownload();
        }

        // Add README with credits
        $readme = $this->generatePhotoCreditsReadme($band);
        $zip->addFromString('README.txt', $readme);

        $zip->close();

        return $path;
    }

    /**
     * Track metric
     */
    public function trackMetric(
        Band $band,
        string $type,
        int $value = 1,
        ?array $metadata = null
    ): void {
        BandProfileAnalytic::create([
            'band_id' => $band->id,
            'metric_type' => $type,
            'metric_value' => $value,
            'date' => now()->toDateString(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Submit booking inquiry
     */
    public function submitBookingInquiry(
        Band $band,
        array $data
    ): BandBookingInquiry {
        $inquiry = BandBookingInquiry::create([
            'band_id' => $band->id,
            'inquirer_name' => $data['name'],
            'inquirer_email' => $data['email'],
            'inquirer_phone' => $data['phone'] ?? null,
            'venue_name' => $data['venue'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'message' => $data['message'],
            'status' => 'new',
        ]);

        // Notify band
        $band->members->each(function ($member) use ($inquiry) {
            $member->user->notify(new BandBookingInquiryNotification($inquiry));
        });

        // Track metric
        $this->trackMetric($band, 'inquiry_submission');

        return $inquiry;
    }

    /**
     * Calculate storage used by band
     */
    public function calculateStorageUsed(Band $band): int
    {
        $totalBytes = 0;

        // Press photos
        foreach ($band->pressPhotos as $photo) {
            $totalBytes += $photo->getMedia('press_photos')->sum('size');
        }

        // Discography cover art
        foreach ($band->discography as $item) {
            $totalBytes += $item->getMedia('cover_art')->sum('size');
        }

        // Tech riders
        foreach ($band->techRiders as $rider) {
            $totalBytes += $rider->getMedia('tech_riders')->sum('size');
        }

        // Custom images (header, favicon, etc.)
        $totalBytes += $band->getMedia('custom_images')->sum('size');

        return (int)($totalBytes / 1024 / 1024); // Convert to MB
    }

    /**
     * Update storage usage
     */
    public function updateStorageUsage(Band $band): void
    {
        if (!$band->profileSubscription) {
            return;
        }

        $used = $this->calculateStorageUsed($band);

        $band->profileSubscription->update([
            'storage_used_mb' => $used,
        ]);

        // Send warning if near limit
        if ($band->profileSubscription->storage_usage_percentage > 90) {
            $this->sendStorageWarning($band);
        }
    }

    /**
     * Validate subdomain
     */
    protected function validateSubdomain(string $subdomain): bool
    {
        // Check format
        if (!preg_match('/^[a-z0-9-]+$/', $subdomain)) {
            return false;
        }

        // Reserved subdomains
        $reserved = ['www', 'mail', 'admin', 'api', 'app', 'member', 'store'];
        if (in_array($subdomain, $reserved)) {
            return false;
        }

        // Check availability
        return !BandProfileSubscription::where('subdomain', $subdomain)->exists();
    }

    /**
     * Get analytics summary
     */
    public function getAnalyticsSummary(Band $band, Carbon $start, Carbon $end): array
    {
        $metrics = BandProfileAnalytic::where('band_id', $band->id)
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy('metric_type');

        return [
            'profile_views' => $metrics->get('profile_view')?->sum('metric_value') ?? 0,
            'epk_downloads' => $metrics->get('epk_download')?->sum('metric_value') ?? 0,
            'inquiries' => $metrics->get('inquiry_submission')?->sum('metric_value') ?? 0,
            'media_plays' => $metrics->get('media_play')?->sum('metric_value') ?? 0,
            'link_clicks' => $metrics->get('link_click')?->sum('metric_value') ?? 0,
        ];
    }

    /**
     * Generate photo credits README
     */
    protected function generatePhotoCreditsReadme(Band $band): string
    {
        $content = "{$band->name} - Press Photos\n";
        $content .= str_repeat('=', strlen($content) - 1) . "\n\n";
        $content .= "Downloaded from: {$band->profile_url}\n";
        $content .= "Date: " . now()->format('F j, Y') . "\n\n";
        $content .= "Photo Credits:\n";
        $content .= str_repeat('-', 40) . "\n\n";

        foreach ($band->pressPhotos as $photo) {
            $content .= "{$photo->title}\n";
            if ($photo->photographer_name) {
                $content .= "Photographer: {$photo->photographer_name}\n";
            }
            if ($photo->photographer_credit_url) {
                $content .= "Website: {$photo->photographer_credit_url}\n";
            }
            $content .= "\n";
        }

        $content .= "\nPlease credit photographers when using these images.\n";

        return $content;
    }
}
```

## Public Profile Pages

### Profile Routing
```php
// routes/web.php

// Subdomain routing
Route::domain('{subdomain}.corvmc.org')->group(function () {
    Route::get('/', [BandProfileController::class, 'subdomain'])->name('band.subdomain');
});

// Standard routing
Route::get('/bands/{band:slug}', [BandProfileController::class, 'show'])->name('bands.show');
Route::get('/bands/{band:slug}/epk/download', [BandProfileController::class, 'downloadEpk'])->name('bands.epk.download');
Route::get('/bands/{band:slug}/photos/download', [BandProfileController::class, 'downloadPhotos'])->name('bands.photos.download');
Route::post('/bands/{band:slug}/booking-inquiry', [BandProfileController::class, 'submitInquiry'])->name('bands.inquiry.submit');
```

### Profile View
- Hero section with featured image/video
- Bio sections (collapsible or tabbed)
- Embedded audio player for demos
- Photo gallery with lightbox
- Discography with streaming links
- Press coverage carousel
- Upcoming tour dates
- Booking inquiry form
- Social media links
- Download EPK/Photos buttons

### EPK Features
- Shareable URL with access tracking
- PDF download
- Embed code for sections
- Print-friendly layout
- Mobile responsive

## Filament Resources

### BandProfileSubscriptionResource
- Location: `/member/band-profiles/subscriptions`
- Admin: View all subscriptions, manage tiers
- Band Members: Own band subscription only
- Upgrade/downgrade functionality
- Billing history
- Storage usage monitoring

### BandEpkManagerResource
- Location: `/member/band-profiles/epk`
- Only accessible to band members
- Manage all EPK sections
- Media upload interface
- Bio editor (multiple lengths)
- Press photo management
- Discography entries
- Tour dates (auto-sync from Productions)
- Tech rider uploads

### BandCustomizationResource
- Location: `/member/band-profiles/customize`
- Template selector
- CSS editor with live preview
- Color scheme picker
- Font selection
- Layout options
- OG tag editor

### BandBookingInquiryResource
- Location: `/member/band-profiles/inquiries`
- Inbox for booking inquiries
- Mark as replied/booked/declined
- Internal notes
- Filter by status

### BandProfileAnalyticsResource
- Location: `/member/band-profiles/analytics`
- Dashboard with charts
- Date range filtering
- Export to CSV
- Metric breakdowns

## Widgets

### BandProfileStatsWidget
- Profile views (week/month)
- EPK downloads
- Booking inquiries
- Top referrers

### StorageUsageWidget
- Current usage vs. limit
- Visual progress bar
- Upgrade prompt if near limit

### RecentBookingInquiriesWidget
- Latest 5 inquiries
- Quick reply action
- Status badges

## Commands

### Sync Tour Dates
```bash
php artisan band-profiles:sync-tour-dates
```
- Auto-create BandTourDate from Productions
- Update existing dates
- Mark past dates

### Update Storage Usage
```bash
php artisan band-profiles:update-storage
```
- Recalculate storage for all bands
- Send warnings if over limit

### Generate EPK Previews
```bash
php artisan band-profiles:generate-previews
```
- Create preview images for templates
- Regenerate OG images

## Notifications

### BandBookingInquiryNotification
- Sent to all band members
- Inquiry details
- Quick reply link

### StorageWarningNotification
- Sent when 90% storage used
- Upgrade prompts
- Cleanup suggestions

### SubscriptionRenewalNotification
- Sent 7 days before renewal
- Payment amount
- Update payment method link

### SubscriptionCancelledNotification
- Sent when subscription cancelled
- End date
- Resubscribe link

## Integration Points

### Productions
- Auto-sync tour dates
- Link EPK from production pages
- Cross-promote band profiles

### Merchandise
- Show band merch on profile
- Link to store

### Member Profiles
- Show member's bands with premium profiles
- Cross-link profiles

### Publications
- Link band spotlights to profiles
- Embed EPK sections in articles

## Permissions

### Roles
- `band_member` - Access to own band's EPK manager

### Abilities
- `manage_band_epk` - Band members only (verified)
- `view_band_analytics` - Band members only
- `manage_band_subscriptions` - Band members only
- `reply_to_inquiries` - Band members only

### Access Control
- All EPK management requires band membership
- Multi-band members select active band
- Subscription changes require payment method

## Pricing Strategy

### Tiers
**Free Tier**
- Basic directory listing
- Standard information
- No storage limit for basics
- No customization

**Premium Tier** ($15/month or $150/year)
- Custom subdomain
- 1GB storage
- Template library
- Standard customization
- Analytics dashboard
- EPK tools

**Pro Tier** ($25/month or $250/year)
- Everything in Premium
- 5GB storage
- Custom CSS editor
- Priority support
- Advanced analytics
- Multiple tech riders

### Discounts
- **Sustaining Members**: 20% off
- **Annual Billing**: ~17% off (2 months free)
- **Multi-band**: 10% off each additional band

## Implementation Estimates

### Phase 1: Core Models & Subscriptions (20-26 hours)
- Database migrations
- Models and relationships
- Stripe subscription integration
- Tier management

### Phase 2: EPK Content Management (18-24 hours)
- Bio, photos, discography models
- Media upload handling
- Tour dates and press items
- Tech rider uploads

### Phase 3: Customization System (16-22 hours)
- Template system
- CSS editor with preview
- Color schemes and fonts
- Subdomain configuration

### Phase 4: Public Profile Pages (20-26 hours)
- Profile view with customization
- EPK layout and sections
- Responsive design
- SEO optimization

### Phase 5: EPK Generation (12-16 hours)
- PDF export
- Press photo package ZIP
- Embed codes
- Print layouts

### Phase 6: Booking Inquiry System (10-14 hours)
- Contact forms
- Inquiry management
- Email notifications
- Status workflow

### Phase 7: Analytics (12-16 hours)
- Metric tracking
- Dashboard widgets
- Export functionality
- Charts and visualizations

### Phase 8: Filament Resources (18-24 hours)
- Band member portal
- EPK manager interface
- Customization tools
- Inquiry inbox

### Phase 9: Storage Management (8-12 hours)
- Usage calculation
- Quota enforcement
- Warnings and alerts
- Cleanup tools

### Phase 10: Testing & Polish (12-16 hours)
- Feature tests
- Subdomain routing tests
- Payment flow tests
- Documentation

**Total Estimate: 146-196 hours**

## Future Enhancements

### Advanced Features
- Video hosting (alternative to embeds)
- Podcast RSS feed integration
- Mailing list integration
- Fan club membership
- Exclusive content for subscribers
- Live stream embedding
- 360° venue photos
- VR/AR experiences

### Discovery & Networking
- Genre-based directory
- Collaborative opportunities board
- Similar band recommendations
- Venue/promoter discovery tools
- Tour routing optimizer
- Shared bill suggestions

### Business Tools
- Contract templates
- Settlement calculators
- Set list builder
- Rider generator (from templates)
- Advance notification system
- Travel/lodging booking integration

### Analytics Enhancements
- Conversion tracking (inquiry → booking)
- Audience demographics
- Geographic heatmaps
- Social media analytics
- Email campaign integration
- A/B testing for profiles

### Integration
- Direct booking calendar sync (Google/Apple)
- Bandsintown integration
- Spotify for Artists data
- SoundExchange reporting
- PRO integration (ASCAP/BMI)
- Streaming royalty aggregation
