<?php

namespace App\Models;

use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelFlags\Models\Concerns\HasFlags;
use Spatie\Tags\HasTags;

/**
 * Represents a band profile in the application.
 * It includes details about the band's name, bio, links, and contact information.
 * The band can have multiple members and exactly one owner.
 */

class BandProfile extends Model implements HasMedia
{
    use HasFlags, HasTags, InteractsWithMedia;

    protected $fillable = [
        'name',
        'bio',
        'links',
        'contact',
        'hometown',
        'owner_id',
        'visibility',
    ];

    public function members()
    {
        return $this->hasMany(User::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function getGenresAttribute()
    {
        return $this->tagsWithType('genre');
    }
}
