<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $display_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LocalResource> $resources
 * @property-read int|null $resources_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceList newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceList newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceList onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceList ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceList published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceList query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceList withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceList withoutTrashed()
 * @method static \Database\Factories\ResourceListFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class ResourceList extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'published_at',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LocalResource::class);
    }

    public function publishedResources(): HasMany
    {
        return $this->resources()->published()->ordered();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }
}
