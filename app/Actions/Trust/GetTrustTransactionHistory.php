<?php

namespace App\Actions\Trust;

use App\Models\TrustTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTrustTransactionHistory
{
    use AsAction;

    /**
     * Get trust transaction history for user.
     */
    public function handle(
        User $user,
        ?string $contentType = null,
        ?int $limit = 50
    ): Collection {
        $query = TrustTransaction::where('user_id', $user->id);

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        return $query->latest()
            ->limit($limit)
            ->get();
    }
}
