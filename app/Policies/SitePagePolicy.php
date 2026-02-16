<?php

namespace App\Policies;

use App\Models\SitePage;
use App\Models\User;

class SitePagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, SitePage $sitePage): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SitePage $sitePage): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, SitePage $sitePage): bool
    {
        return false;
    }
}
