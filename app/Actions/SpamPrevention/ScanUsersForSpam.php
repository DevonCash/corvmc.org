<?php

namespace App\Actions\SpamPrevention;

use App\Data\SpamCheckResultData;
use App\Models\User;
use Illuminate\Support\Facades\{DB, Log};
use Lorisleiva\Actions\Concerns\AsAction;

class ScanUsersForSpam
{
    use AsAction;

    /**
     * Scan users for spam emails
     *
     * @param  bool  $dryRun  If true, only check without deleting
     * @param  bool  $removeSpam  If true and not dry run, delete identified spam users
     * @return array Scan results with statistics
     */
    public function handle(bool $dryRun = true, bool $removeSpam = false): array
    {
        $results = [];
        $totalScanned = 0;
        $spamFound = 0;
        $deleted = 0;
        $errors = 0;

        // Get all users (exclude soft-deleted unless specifically requested)
        $users = User::query()
            ->orderBy('created_at', 'asc')
            ->get();

        $totalScanned = $users->count();

        foreach ($users as $user) {
            /** @var SpamCheckResultData $checkResult */
            $checkResult = CheckEmailAgainstStopForumSpam::run($user->email);
            // Track errors
            if ($checkResult->hasError()) {
                $errors++;
                continue;
            }

            // If spam is detected
            if ($checkResult->is_spam) {
                $spamFound++;

                $results[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'frequency' => $checkResult->frequency,
                    'last_seen' => $checkResult->last_seen,
                    'deleted' => false,
                ];

                // Delete if not dry run and remove flag is set
                if (! $dryRun && $removeSpam) {
                    try {
                        DB::transaction(function () use ($user) {
                            // Force delete to completely remove spam accounts
                            $user->forceDelete();
                        });

                        // Mark as deleted in results
                        $results[count($results) - 1]['deleted'] = true;
                        $deleted++;
                    } catch (\Exception $e) {
                        Log::error('Failed to delete spam user', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Add small delay to avoid rate limiting (100ms between requests)
            usleep(100000);
        }

        return [
            'total_scanned' => $totalScanned,
            'spam_found' => $spamFound,
            'deleted' => $deleted,
            'errors' => $errors,
            'spam_percentage' => $totalScanned > 0 ? round(($spamFound / $totalScanned) * 100, 2) : 0,
            'results' => collect($results),
            'dry_run' => $dryRun,
        ];
    }
}
