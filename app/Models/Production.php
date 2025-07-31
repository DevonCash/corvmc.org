<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class Production extends Model implements HasMedia
{
    use HasTags, InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'doors_time',
        'location',
        'status',
        'published_at',
        'manager_id',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function performers()
    {
        return $this->belongsToMany(BandProfile::class, 'production_bands');
    }

    public function reservation()
    {
        return $this->hasOne(Reservation::class);
    }

    public function getGenresAttribute()
    {
        return $this->tagsWithType('genre');
    }
}
