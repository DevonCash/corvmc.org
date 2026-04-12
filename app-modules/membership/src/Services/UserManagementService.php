<?php

namespace CorvMC\Membership\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserManagementService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create($data);
            
            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }
            
            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }
        
        return $user->fresh();
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function forceDelete(User $user): bool
    {
        return $user->forceDelete();
    }

    public function restore(User $user): bool
    {
        return $user->restore();
    }

    public function bulkUpdate(array $userIds, array $data): int
    {
        return User::whereIn('id', $userIds)->update($data);
    }

    public function bulkDelete(array $userIds): int
    {
        return User::whereIn('id', $userIds)->delete();
    }

    public function getUsers(array $filters = []): Collection
    {
        $query = User::query();
        
        if (isset($filters['role'])) {
            $query->role($filters['role']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                  ->orWhere('email', 'like', '%'.$filters['search'].'%');
            });
        }
        
        return $query->get();
    }

    public function getUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::whereNotNull('email_verified_at')->count(),
            'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'by_role' => User::selectRaw('count(*) as count')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->groupBy('model_has_roles.role_id')
                ->pluck('count'),
        ];
    }
}