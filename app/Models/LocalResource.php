<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $resource_list_id
 * @property string $name
 * @property string|null $description
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $website
 * @property string|null $address
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\ResourceList $resourceList
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocalResource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocalResource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocalResource onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocalResource ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocalResource published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocalResource query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocalResource withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocalResource withoutTrashed()
 * @method static \Database\Factories\LocalResourceFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class LocalResource extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resource_list_id',
        'name',
        'description',
        'contact_name',
        'contact_email',
        'contact_phone',
        'website',
        'address',
        'published_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function resourceList(): BelongsTo
    {
        return $this->belongsTo(ResourceList::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }
}
