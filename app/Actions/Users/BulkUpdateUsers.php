<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkUpdateUsers
{
    use AsAction;

    /**
     * Bulk update users.
     */
    public function handle(array $userIds, array $data): int
    {
        return DB::transaction(function () use ($userIds, $data) {
            $updatedCount = 0;

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    UpdateUser::run($user, $data);
                    $updatedCount++;
                }
            }

            return $updatedCount;
        });
    }
}
