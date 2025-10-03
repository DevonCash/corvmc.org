# Community Music Publication System Design

## Overview

A comprehensive content management system for producing, managing, and distributing community music publications - from individual zines to quarterly magazines. Supports editorial workflows, contributor management, content archives, sponsor relationships, and both print and digital distribution.

## Goals

- **Editorial Workflow**: Pitch → Draft → Review → Edit → Publish pipeline
- **Content Management**: Archive of articles, issues, and zines with search/filtering
- **Contributor System**: Writer onboarding, assignment tracking, compensation
- **Sponsor Management**: Ad placement, sponsor credits, revenue tracking
- **Distribution Tracking**: Print runs, distribution locations, pickup metrics
- **Digital Archive**: Searchable, tagged, public-facing content library
- **Integration**: Connect with member profiles, events, productions

## Core Features

### 1. Editorial Workflow
- Article pitch submission and review
- Assignment and deadline management
- Draft submission and revision tracking
- Editorial review and feedback
- Publication scheduling
- Version control for articles

### 2. Publication Types
- **Zines**: Single-topic, quick-turnaround publications
- **Magazines**: Multi-article quarterly issues
- **Digital-only**: Web articles and blog posts
- **Special editions**: Themed compilations

### 3. Content Organization
- Article categorization (education, spotlight, business, scene)
- Skill level tagging (beginner, intermediate, advanced)
- Topic taxonomy (recording, live sound, guitar, etc.)
- Searchable archive
- Related content suggestions

### 4. Contributor Management
- Writer profiles and portfolios
- Pitch tracking and history
- Assignment management
- Payment/compensation tracking
- Contributor credits and bylines
- Author bios and links

### 5. Sponsor Integration
- Sponsor profiles and contacts
- Ad placement management
- Sponsorship tiers and pricing
- Invoice generation
- Sponsor credit tracking
- Renewal reminders

### 6. Distribution Management
- Print run tracking (quantity, costs)
- Distribution location database
- Pickup/restock tracking
- Digital download metrics
- QR code generation for physical→digital

## Database Schema

### Tables

#### `publications`
```
id - bigint primary key
title - string
slug - string unique
publication_type - enum (zine, magazine, digital, special)
issue_number - integer nullable (for magazines)
volume_number - integer nullable (for magazines)
description - text
cover_theme - string nullable
status - enum (planning, in_production, published, archived)
published_at - timestamp nullable
editor_id - foreign key to users
print_quantity - integer nullable
print_cost - integer nullable (cents)
distribution_notes - text nullable
isbn - string nullable
settings - json (publication-specific settings)
created_at, updated_at, deleted_at
```

#### `articles`
```
id - bigint primary key
publication_id - foreign key nullable (null = not yet assigned)
title - string
slug - string
subtitle - string nullable
content - longtext
excerpt - text nullable
article_type - enum (educational, spotlight, business, scene, review, interview)
skill_level - enum (beginner, intermediate, advanced, all) nullable
word_count - integer
read_time_minutes - integer
status - enum (pitch, assigned, draft, review, revision, approved, published, archived)
author_id - foreign key to users
editor_id - foreign key to users nullable
submitted_at - timestamp nullable
approved_at - timestamp nullable
published_at - timestamp nullable
featured - boolean (default false)
settings - json (article-specific metadata)
created_at, updated_at, deleted_at
```

#### `article_pitches`
```
id - bigint primary key
article_id - foreign key (becomes full article if accepted)
pitcher_id - foreign key to users
title - string
pitch_description - text
target_publication_type - enum (zine, magazine, either)
estimated_word_count - integer nullable
expertise_notes - text nullable (why they're qualified)
status - enum (submitted, reviewing, accepted, declined, withdrawn)
reviewed_by - foreign key to users nullable
reviewed_at - timestamp nullable
review_notes - text nullable
created_at, updated_at
```

#### `article_revisions`
```
id - bigint primary key
article_id - foreign key
version - integer
content - longtext
changed_by - foreign key to users
change_notes - text nullable
created_at
```

#### `article_contributors`
```
id - bigint primary key
article_id - foreign key
user_id - foreign key
role - enum (author, co_author, editor, photographer, illustrator, interviewer)
byline_name - string (display name for credits)
byline_order - integer (for sorting multiple contributors)
compensation_amount - integer nullable (cents)
compensation_type - enum (none, flat_fee, per_word, trade) nullable
compensation_status - enum (pending, paid, waived) nullable
created_at, updated_at
```

#### `publication_sponsors`
```
id - bigint primary key
publication_id - foreign key
sponsor_id - foreign key
sponsorship_tier - enum (supporter, bronze, silver, gold, title)
amount - integer (cents)
ad_placement - enum (none, credit_only, small_ad, half_page, full_page)
ad_content - text nullable (ad copy or image path)
invoice_sent_at - timestamp nullable
paid_at - timestamp nullable
created_at, updated_at
```

#### `sponsors`
```
id - bigint primary key
name - string
slug - string unique
description - text nullable
contact_name - string nullable
contact_email - string
contact_phone - string nullable
website_url - string nullable
logo_path - string nullable
business_type - enum (venue, studio, shop, service, education, other)
is_active - boolean (default true)
notes - text nullable
created_at, updated_at, deleted_at
```

#### `distribution_locations`
```
id - bigint primary key
name - string
location_type - enum (zine_library, coffee_shop, venue, cmc_office, record_store, other)
address - string
contact_name - string nullable
contact_email - string nullable
contact_phone - string nullable
capacity - integer (max zines they can hold)
is_active - boolean (default true)
notes - text nullable
created_at, updated_at, deleted_at
```

#### `distribution_batches`
```
id - bigint primary key
publication_id - foreign key
distribution_location_id - foreign key
quantity_delivered - integer
delivered_at - timestamp
delivered_by - foreign key to users nullable
restocked_at - timestamp nullable (if refilled)
quantity_restocked - integer nullable
notes - text nullable
created_at, updated_at
```

#### `publication_analytics`
```
id - bigint primary key
publication_id - foreign key nullable
article_id - foreign key nullable (for digital articles)
metric_type - enum (pdf_download, web_view, print_pickup_estimate, share)
metric_value - integer (count)
date - date
metadata - json nullable (referrer, location, etc.)
created_at
```

#### `content_tags` (extends existing tags)
- Use existing `spatie/laravel-tags` for article topics
- Tag types: topics, genres, skills, equipment, venues, events
- Allows cross-referencing with member profiles, productions, etc.

## Models & Relationships

### Publication
```php
class Publication extends Model
{
    use SoftDeletes, LogsActivity, HasSlug, InteractsWithMedia;

    protected $casts = [
        'published_at' => 'datetime',
        'print_quantity' => 'integer',
        'print_cost' => 'integer',
        'issue_number' => 'integer',
        'volume_number' => 'integer',
        'settings' => 'array',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function articles()
    {
        return $this->hasMany(Article::class)->orderBy('published_at');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function sponsors()
    {
        return $this->belongsToMany(Sponsor::class, 'publication_sponsors')
            ->withPivot(['sponsorship_tier', 'amount', 'ad_placement', 'paid_at'])
            ->withTimestamps();
    }

    public function distributionBatches()
    {
        return $this->hasMany(DistributionBatch::class);
    }

    public function analytics()
    {
        return $this->hasMany(PublicationAnalytic::class);
    }

    /**
     * Get total distribution quantity
     */
    public function getTotalDistributedAttribute(): int
    {
        return $this->distributionBatches()->sum('quantity_delivered');
    }

    /**
     * Get total sponsor revenue
     */
    public function getSponsorRevenueAttribute(): int
    {
        return $this->sponsors()
            ->wherePivot('paid_at', '!=', null)
            ->sum('publication_sponsors.amount');
    }

    /**
     * Check if published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' &&
               $this->published_at &&
               $this->published_at->isPast();
    }

    /**
     * Get cover image
     */
    public function getCoverImageAttribute()
    {
        return $this->getFirstMediaUrl('cover');
    }
}
```

### Article
```php
class Article extends Model
{
    use SoftDeletes, LogsActivity, HasSlug, HasTags, InteractsWithMedia, Revisionable;

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'featured' => 'boolean',
        'word_count' => 'integer',
        'read_time_minutes' => 'integer',
        'settings' => 'array',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    protected static function booted()
    {
        static::saving(function ($article) {
            // Auto-calculate word count and read time
            $article->word_count = str_word_count(strip_tags($article->content));
            $article->read_time_minutes = max(1, round($article->word_count / 200));
        });
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function contributors()
    {
        return $this->hasMany(ArticleContributor::class)->orderBy('byline_order');
    }

    public function pitch()
    {
        return $this->hasOne(ArticlePitch::class);
    }

    public function revisions()
    {
        return $this->hasMany(ArticleRevision::class)->orderByDesc('version');
    }

    public function analytics()
    {
        return $this->hasMany(PublicationAnalytic::class);
    }

    /**
     * Get all contributors including primary author
     */
    public function getAllContributorsAttribute(): Collection
    {
        $contributors = $this->contributors;

        // Add primary author if not already in contributors
        if (!$contributors->where('user_id', $this->author_id)->count()) {
            $contributors->prepend([
                'user' => $this->author,
                'role' => 'author',
                'byline_order' => 0,
            ]);
        }

        return $contributors->sortBy('byline_order');
    }

    /**
     * Get formatted byline
     */
    public function getBylineAttribute(): string
    {
        $contributors = $this->all_contributors;

        if ($contributors->count() === 1) {
            return 'By ' . $contributors->first()->byline_name;
        }

        $authors = $contributors->where('role', 'author');
        $others = $contributors->whereNotIn('role', ['author']);

        $byline = 'By ' . $authors->pluck('byline_name')->join(', ', ' and ');

        if ($others->count()) {
            $byline .= ' • ' . $others->pluck('byline_name')->join(', ');
        }

        return $byline;
    }

    /**
     * Check if published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' &&
               $this->published_at &&
               $this->published_at->isPast();
    }

    /**
     * Get article URL
     */
    public function getUrlAttribute(): string
    {
        if ($this->publication) {
            return route('publications.show', [$this->publication->slug, $this->slug]);
        }
        return route('articles.show', $this->slug);
    }

    /**
     * Scopes
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('article_type', $type);
    }

    public function scopeBySkillLevel($query, string $level)
    {
        return $query->where('skill_level', $level);
    }
}
```

### ArticlePitch
```php
class ArticlePitch extends Model
{
    use LogsActivity;

    protected $casts = [
        'reviewed_at' => 'datetime',
        'estimated_word_count' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function pitcher()
    {
        return $this->belongsTo(User::class, 'pitcher_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Accept pitch and convert to article
     */
    public function accept(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => 'accepted',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $this->article->update([
            'status' => 'assigned',
            'editor_id' => $reviewer->id,
        ]);
    }

    /**
     * Decline pitch
     */
    public function decline(User $reviewer, string $reason): void
    {
        $this->update([
            'status' => 'declined',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);
    }
}
```

### ArticleRevision
```php
class ArticleRevision extends Model
{
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
```

### ArticleContributor
```php
class ArticleContributor extends Model
{
    protected $casts = [
        'compensation_amount' => 'integer',
        'byline_order' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if contributor has been paid
     */
    public function isPaid(): bool
    {
        return $this->compensation_status === 'paid';
    }

    /**
     * Mark as paid
     */
    public function markPaid(): void
    {
        $this->update(['compensation_status' => 'paid']);
    }
}
```

### Sponsor
```php
class Sponsor extends Model
{
    use SoftDeletes, LogsActivity, HasSlug, InteractsWithMedia;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function publications()
    {
        return $this->belongsToMany(Publication::class, 'publication_sponsors')
            ->withPivot(['sponsorship_tier', 'amount', 'ad_placement', 'paid_at'])
            ->withTimestamps();
    }

    /**
     * Get total sponsorship amount
     */
    public function getTotalSponsorshipAttribute(): int
    {
        return $this->publications()
            ->wherePivot('paid_at', '!=', null)
            ->sum('publication_sponsors.amount');
    }

    /**
     * Get logo URL
     */
    public function getLogoUrlAttribute()
    {
        return $this->getFirstMediaUrl('logo');
    }
}
```

### DistributionLocation
```php
class DistributionLocation extends Model
{
    use SoftDeletes, LogsActivity;

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function batches()
    {
        return $this->hasMany(DistributionBatch::class);
    }

    /**
     * Get total distributed to this location
     */
    public function getTotalDistributedAttribute(): int
    {
        return $this->batches()->sum('quantity_delivered');
    }

    /**
     * Get current inventory estimate
     */
    public function getCurrentInventoryAttribute(): int
    {
        $delivered = $this->batches()
            ->where('delivered_at', '>=', now()->subMonths(3))
            ->sum('quantity_delivered');

        $restocked = $this->batches()
            ->whereNotNull('restocked_at')
            ->where('restocked_at', '>=', now()->subMonths(3))
            ->sum('quantity_restocked');

        // Rough estimate - actual pickup tracking would need physical audits
        return $delivered + $restocked;
    }
}
```

### DistributionBatch
```php
class DistributionBatch extends Model
{
    protected $casts = [
        'delivered_at' => 'datetime',
        'restocked_at' => 'datetime',
        'quantity_delivered' => 'integer',
        'quantity_restocked' => 'integer',
    ];

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function location()
    {
        return $this->belongsTo(DistributionLocation::class);
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }
}
```

### PublicationAnalytic
```php
class PublicationAnalytic extends Model
{
    public $timestamps = false;

    protected $casts = [
        'date' => 'date',
        'metric_value' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
```

## Service Layer

### PublicationService

```php
class PublicationService
{
    /**
     * Submit article pitch
     */
    public function submitPitch(
        User $pitcher,
        string $title,
        string $description,
        string $targetType = 'either',
        ?int $estimatedWordCount = null,
        ?string $expertiseNotes = null
    ): ArticlePitch {
        // Create article stub
        $article = Article::create([
            'title' => $title,
            'author_id' => $pitcher->id,
            'status' => 'pitch',
        ]);

        $pitch = ArticlePitch::create([
            'article_id' => $article->id,
            'pitcher_id' => $pitcher->id,
            'title' => $title,
            'pitch_description' => $description,
            'target_publication_type' => $targetType,
            'estimated_word_count' => $estimatedWordCount,
            'expertise_notes' => $expertiseNotes,
            'status' => 'submitted',
        ]);

        // Notify editors
        $this->notifyEditorsOfNewPitch($pitch);

        return $pitch;
    }

    /**
     * Accept pitch
     */
    public function acceptPitch(ArticlePitch $pitch, User $editor, ?string $notes = null): Article
    {
        $pitch->accept($editor, $notes);

        $pitcher = $pitch->pitcher;
        $pitcher->notify(new ArticlePitchAcceptedNotification($pitch));

        return $pitch->article;
    }

    /**
     * Submit article draft
     */
    public function submitDraft(Article $article, string $content): void
    {
        $article->update([
            'content' => $content,
            'status' => 'review',
            'submitted_at' => now(),
        ]);

        // Create revision
        $this->createRevision($article, $article->author, 'Initial submission');

        // Notify editor
        if ($article->editor) {
            $article->editor->notify(new ArticleDraftSubmittedNotification($article));
        }
    }

    /**
     * Create article revision
     */
    public function createRevision(Article $article, User $user, ?string $notes = null): ArticleRevision
    {
        $latestVersion = $article->revisions()->max('version') ?? 0;

        return ArticleRevision::create([
            'article_id' => $article->id,
            'version' => $latestVersion + 1,
            'content' => $article->content,
            'changed_by' => $user->id,
            'change_notes' => $notes,
        ]);
    }

    /**
     * Request revisions from author
     */
    public function requestRevisions(Article $article, User $editor, string $feedback): void
    {
        $article->update([
            'status' => 'revision',
            'editor_id' => $editor->id,
        ]);

        $article->author->notify(new ArticleRevisionsRequestedNotification($article, $feedback));
    }

    /**
     * Approve article
     */
    public function approveArticle(Article $article, User $editor): void
    {
        $article->update([
            'status' => 'approved',
            'approved_at' => now(),
            'editor_id' => $editor->id,
        ]);

        $article->author->notify(new ArticleApprovedNotification($article));
    }

    /**
     * Publish article
     */
    public function publishArticle(Article $article, ?Carbon $publishAt = null): void
    {
        $article->update([
            'status' => 'published',
            'published_at' => $publishAt ?? now(),
        ]);

        // Track analytics
        $this->trackMetric($article, 'published', 1);

        // Notify author
        $article->author->notify(new ArticlePublishedNotification($article));
    }

    /**
     * Publish publication (zine/magazine)
     */
    public function publishPublication(Publication $publication, ?Carbon $publishAt = null): void
    {
        $publication->update([
            'status' => 'published',
            'published_at' => $publishAt ?? now(),
        ]);

        // Publish all assigned articles
        foreach ($publication->articles as $article) {
            if ($article->status === 'approved') {
                $this->publishArticle($article, $publishAt);
            }
        }

        // Track analytics
        $this->trackMetric($publication, 'published', 1);
    }

    /**
     * Add sponsor to publication
     */
    public function addSponsor(
        Publication $publication,
        Sponsor $sponsor,
        string $tier,
        int $amount,
        string $adPlacement = 'credit_only',
        ?string $adContent = null
    ): void {
        $publication->sponsors()->attach($sponsor->id, [
            'sponsorship_tier' => $tier,
            'amount' => $amount,
            'ad_placement' => $adPlacement,
            'ad_content' => $adContent,
        ]);

        // Create transaction record
        Transaction::create([
            'user_id' => null,
            'amount' => $amount,
            'type' => 'sponsorship',
            'status' => 'pending',
            'description' => "Sponsorship: {$sponsor->name} - {$publication->title}",
            'transactionable_type' => Publication::class,
            'transactionable_id' => $publication->id,
        ]);

        // Send invoice
        $sponsor->notify(new SponsorInvoiceNotification($publication, $amount, $tier));
    }

    /**
     * Record distribution batch
     */
    public function distributePublication(
        Publication $publication,
        DistributionLocation $location,
        int $quantity,
        User $deliveredBy
    ): DistributionBatch {
        return DistributionBatch::create([
            'publication_id' => $publication->id,
            'distribution_location_id' => $location->id,
            'quantity_delivered' => $quantity,
            'delivered_at' => now(),
            'delivered_by' => $deliveredBy->id,
        ]);
    }

    /**
     * Track metric
     */
    public function trackMetric($model, string $type, int $value = 1, ?array $metadata = null): void
    {
        $data = [
            'metric_type' => $type,
            'metric_value' => $value,
            'date' => now()->toDateString(),
            'metadata' => $metadata,
        ];

        if ($model instanceof Publication) {
            $data['publication_id'] = $model->id;
        } elseif ($model instanceof Article) {
            $data['article_id'] = $model->id;
        }

        PublicationAnalytic::create($data);
    }

    /**
     * Get popular articles
     */
    public function getPopularArticles(int $limit = 10, int $days = 30): Collection
    {
        $since = now()->subDays($days);

        return Article::published()
            ->withCount([
                'analytics as total_views' => function ($query) use ($since) {
                    $query->where('metric_type', 'web_view')
                        ->where('date', '>=', $since);
                }
            ])
            ->orderByDesc('total_views')
            ->limit($limit)
            ->get();
    }

    /**
     * Search articles
     */
    public function searchArticles(
        ?string $query = null,
        ?string $type = null,
        ?string $skillLevel = null,
        ?array $tags = null
    ): Collection {
        $articles = Article::published();

        if ($query) {
            $articles->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%")
                    ->orWhere('excerpt', 'like', "%{$query}%");
            });
        }

        if ($type) {
            $articles->byType($type);
        }

        if ($skillLevel) {
            $articles->bySkillLevel($skillLevel);
        }

        if ($tags) {
            $articles->withAnyTags($tags);
        }

        return $articles->orderByDesc('published_at')->get();
    }

    /**
     * Get contributor stats
     */
    public function getContributorStats(User $user): array
    {
        $articles = Article::where('author_id', $user->id)->published()->get();
        $contributions = ArticleContributor::where('user_id', $user->id)->get();

        return [
            'articles_authored' => $articles->count(),
            'total_words' => $articles->sum('word_count'),
            'contributions' => $contributions->count(),
            'total_earned' => $contributions->sum('compensation_amount'),
            'first_published' => $articles->min('published_at'),
            'last_published' => $articles->max('published_at'),
        ];
    }

    /**
     * Notify editors of new pitch
     */
    protected function notifyEditorsOfNewPitch(ArticlePitch $pitch): void
    {
        $editors = User::role('editor')->get();

        foreach ($editors as $editor) {
            $editor->notify(new NewArticlePitchNotification($pitch));
        }
    }
}
```

## Filament Resources

### PublicationResource
- Location: `/member/publications`
- List view with filters (type, status, date)
- Create/edit publications
- Article assignment interface
- Sponsor management
- Distribution tracking
- Analytics dashboard
- PDF export for print

### ArticleResource
- Location: `/member/articles`
- Kanban view by status (pitch, draft, review, approved, published)
- Rich text editor with media embedding
- Tag management
- Contributor assignment
- Revision history viewer
- Preview mode

### ArticlePitchResource
- Location: `/member/pitches`
- Queue for editor review
- Accept/decline actions
- Pitch history per contributor

### SponsorResource
- Location: `/member/sponsors`
- Contact management
- Sponsorship history
- Invoice generation
- Payment tracking

### DistributionLocationResource
- Location: `/member/distribution-locations`
- Location directory
- Inventory estimates
- Restock scheduling

## Public Pages

### Publications Archive
- `/publications` - Browse all publications
- Filter by type, topic, date
- Search functionality
- Featured publications

### Publication Detail
- `/publications/{slug}` - Single publication view
- Table of contents
- Download PDF
- View all articles

### Article Detail
- `/articles/{slug}` or `/publications/{pub-slug}/{article-slug}`
- Full article with formatting
- Author bio
- Related articles
- Share buttons
- Reading progress indicator

### Contributor Profile
- `/contributors/{slug}` - Author portfolio
- Published articles
- Bio and links
- Stats (articles, words written)

### Topics/Tags
- `/topics/{tag}` - Articles by topic
- Skill level filtering
- Chronological or popularity sorting

## Commands

### Generate Publication
```bash
php artisan publication:generate {publication-id} [--pdf] [--web]
```
- Compile articles into publication
- Generate PDF for print
- Generate web version
- Create table of contents

### Track Distribution
```bash
php artisan publication:track-distribution {publication-id}
```
- Log distribution batch
- Update inventory
- Send restock alerts

### Contributor Payments
```bash
php artisan publication:process-payments {publication-id}
```
- Calculate contributor compensation
- Generate payment records
- Send payment notifications

### Analytics Report
```bash
php artisan publication:analytics-report [--publication=] [--period=]
```
- Generate analytics report
- Popular articles
- Distribution metrics
- Sponsor ROI

## Notifications

### NewArticlePitchNotification
- Sent to editors when pitch submitted
- Quick accept/decline actions

### ArticlePitchAcceptedNotification
- Sent to author when pitch accepted
- Next steps and deadline

### ArticlePitchDeclinedNotification
- Sent to author when pitch declined
- Feedback and encouragement

### ArticleDraftSubmittedNotification
- Sent to editor when draft ready
- Review link

### ArticleRevisionsRequestedNotification
- Sent to author when revisions needed
- Editor feedback

### ArticleApprovedNotification
- Sent to author when article approved
- Publication timeline

### ArticlePublishedNotification
- Sent to author and contributors
- Live article link
- Share prompts

### SponsorInvoiceNotification
- Sent to sponsor with invoice
- Payment instructions

### DistributionRestockNeededNotification
- Sent to distribution coordinator
- Location and quantity needed

## Widgets

### EditorialCalendarWidget
- Upcoming deadlines
- Articles by status
- Publication schedule

### ContributorLeaderboardWidget
- Top contributors this month/year
- Most articles, words, engagement

### PopularArticlesWidget
- Most viewed/downloaded
- Trending topics

### SponsorRevenueWidget
- Current sponsor revenue
- Pending invoices
- Renewal opportunities

## Integration Points

### Member Profiles
- Show articles authored
- Contributor badge
- Writing portfolio

### Productions
- Link articles about local shows
- Event coverage
- Band spotlights

### Community Events
- Publication launch events
- Writing workshops
- Contributor meetups

### Credits System
- Reward credits for contributions
- Pitch acceptance bonus
- Publication milestone rewards

## Permissions

### Roles
- `contributor` - Submit pitches, write articles
- `editor` - Review pitches, edit articles, manage publications
- `sponsor_manager` - Manage sponsor relationships
- `distribution_coordinator` - Manage distribution locations and batches

### Abilities
- `submit_pitch`
- `review_pitch`
- `edit_article`
- `publish_article`
- `manage_sponsors`
- `manage_distribution`
- `view_analytics`

## Implementation Estimates

### Phase 1: Core Article System (16-20 hours)
- Database migrations
- Article, ArticlePitch, ArticleRevision models
- Basic PublicationService methods
- ArticleResource with status workflow

### Phase 2: Publication Management (12-16 hours)
- Publication model and relationships
- PublicationResource
- Article assignment to publications
- PDF generation

### Phase 3: Contributor System (8-12 hours)
- ArticleContributor model
- Byline generation
- Compensation tracking
- Contributor stats

### Phase 4: Sponsor System (10-14 hours)
- Sponsor model
- Publication sponsorship relationships
- Invoice generation
- Ad placement management

### Phase 5: Distribution System (8-10 hours)
- DistributionLocation model
- DistributionBatch tracking
- Restock alerts
- Inventory estimates

### Phase 6: Public Pages (14-18 hours)
- Archive pages
- Article/publication detail pages
- Search and filtering
- Contributor profiles
- SEO optimization

### Phase 7: Analytics & Reporting (8-12 hours)
- PublicationAnalytic model
- Tracking implementation
- Analytics widgets
- Report generation

### Phase 8: Commands & Automation (6-8 hours)
- All artisan commands
- Scheduled tasks
- Notifications

### Phase 9: Testing & Polish (8-12 hours)
- Feature tests
- Test command
- UI/UX refinements
- Documentation

**Total Estimate: 90-122 hours**

## Future Enhancements

### Advanced Features
- Multi-language support for articles
- Podcast integration (audio versions of articles)
- Interactive content (quizzes, polls)
- Member comments and discussions
- Article series/collections
- Email newsletter integration
- Paywall for premium content
- Print-on-demand for back issues
- API for third-party access
- Mobile app for reading

### Editorial Features
- Collaborative editing (multiple editors)
- Editorial calendar with planning
- Style guide integration
- Fact-checking workflow
- Legal review process
- Photo editing assignments
- Illustration requests

### Monetization
- Individual article sales
- Subscription tiers
- Sponsored content workflow
- Affiliate link management
- Crowdfunding for special issues

### Community Features
- Reader submission portal
- Letters to the editor
- Article ratings and reviews
- Save for later / reading lists
- Social sharing incentives
- Reader-contributed tags
