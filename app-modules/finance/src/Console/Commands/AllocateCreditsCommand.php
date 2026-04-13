<?php

namespace CorvMC\Finance\Console\Commands;

use App\Models\User;
use CorvMC\Finance\Facades\MemberBenefitService;
use Illuminate\Console\Command;

class AllocateCreditsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:allocate
                            {--user-id= : Allocate credits for a specific user ID}
                            {--dry-run : Show what would be allocated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Allocate monthly credits to all sustaining members (idempotent - safe to run daily)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎫 Allocating Monthly Credits');
        $this->line('═══════════════════════════════');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get users to process
        if ($userId = $this->option('user-id')) {
            $users = User::where('id', $userId)->get();
            if ($users->isEmpty()) {
                $this->error("User ID {$userId} not found");

                return 1;
            }
        } else {
            // Get all sustaining members
            // Operation is idempotent - won't double-allocate in same month
            $users = User::role('sustaining member')->get();
        }

        $this->info("Processing {$users->count()} sustaining member(s)...");
        $this->newLine();

        $allocated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                $hours = MemberBenefitService::getUserMonthlyFreeHours($user);
                $blocks = \CorvMC\SpaceManagement\Models\Reservation::hoursToBlocks($hours);
                $currentBalance = $user->getCreditBalance(\CorvMC\Finance\Enums\CreditType::FreeHours);
                $currentHours = \CorvMC\SpaceManagement\Models\Reservation::blocksToHours($currentBalance);

                if ($this->option('dry-run')) {
                    $this->line("→ Would allocate {$blocks} blocks ({$hours} hours) to {$user->name}");
                    $this->line("  Current balance: {$currentBalance} blocks ({$currentHours} hours)");
                } else {
                    MemberBenefitService::allocateUserMonthlyCredits($user);
                    $this->line("✓ Allocated {$blocks} blocks ({$hours} hours) to {$user->name}");
                    $allocated++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error allocating credits for {$user->name}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("Allocated: {$allocated}");
        $this->line("Errors: {$errors}");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - No changes were made');
        }

        return 0;
    }
}
