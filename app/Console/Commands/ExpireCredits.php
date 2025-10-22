<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\UserCredit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireCredits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire credits that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding expired credits...');

        // Find all credits with non-zero balance that have expired
        $expiredCredits = UserCredit::where('balance', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expiredCredits->isEmpty()) {
            $this->info('No expired credits found.');
            return Command::SUCCESS;
        }

        $totalExpired = 0;

        foreach ($expiredCredits as $credit) {
            DB::transaction(function () use ($credit, &$totalExpired) {
                $expiredAmount = $credit->balance;

                // Create expiration transaction
                CreditTransaction::create([
                    'user_id' => $credit->user_id,
                    'credit_type' => $credit->credit_type,
                    'amount' => -$expiredAmount,
                    'balance_after' => 0,
                    'source' => 'expiration',
                    'description' => "Expired {$expiredAmount} blocks (expired on {$credit->expires_at->toDateString()})",
                    'created_at' => now(),
                ]);

                // Zero out the balance
                $credit->balance = 0;
                $credit->save();

                $totalExpired += $expiredAmount;

                $this->line("Expired {$expiredAmount} blocks for user #{$credit->user_id} ({$credit->credit_type})");
            });
        }

        $this->info("Expired {$totalExpired} total blocks from {$expiredCredits->count()} credit records.");

        return Command::SUCCESS;
    }
}
