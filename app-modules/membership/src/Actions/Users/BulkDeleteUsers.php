<?php

namespace CorvMC\Membership\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkDeleteUsers
{
    use AsAction;

    /**
     * Bulk delete users.
     */
    public function handle(array $userIds): int
    {
        return DB::transaction(function () use ($userIds) {
            $deletedCount = 0;

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    DeleteUser::run($user);
                    $deletedCount++;
                }
            }

            return $deletedCount;
        });
    }
}
