<?php

namespace CorvMC\Moderation\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class MemberVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user) {
            // Only show public profiles to guests
            $builder->where('visibility', 'public');

            return;
        }

        $builder->where(function ($query) use ($user) {
            $query->where('visibility', 'public')
                ->orWhere('user_id', $user->id)
                ->orWhere('visibility', 'members');

            // If user has permission to view private profiles, include them
            if ($user->can('view private member profiles')) {
                $query->orWhere('visibility', 'private');
            }
        });
    }
}
