<?php

namespace CorvMC\Membership\Actions\Users;

use CorvMC\Membership\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUsers
{
    use AsAction;

    /**
     * Get paginated users with filters.
     */
    public function handle(array $filters = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = User::with(['roles', 'profile']);

        // Apply filters
        if (! empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['active'])) {
            if ($filters['active']) {
                $query->whereNull('deleted_at');
            } else {
                $query->onlyTrashed();
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
