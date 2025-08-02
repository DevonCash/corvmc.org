<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OwnedBandsScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * This scope filters out touring bands (those without an owner_id) by default.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotNull('owner_id');
    }
}
