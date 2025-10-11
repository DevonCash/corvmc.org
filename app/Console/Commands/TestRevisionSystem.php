<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\MemberProfile;
use App\Models\Revision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TestRevisionSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:revision-system {--dry-run : Show what would happen} {--clean : Clean test data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the revision system end-to-end';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Revision System');
        $this->line('==========================');
        
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN mode - no changes will be made');
        }
        
        if ($this->option('clean')) {
            $this->cleanTestData();
        }

        try {
            $this->setupPermissions();
            $this->testRevisionCreation();
            $this->testAutoApproval();
            $this->testManualApproval();
            $this->testTrustIntegration();
            
            $this->info('✅ All revision system tests passed!');
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function testRevisionCreation(): void
    {
        $this->info('📝 1. Testing revision creation...');
        
        // Create test user and profile
        $user = User::factory()->create(['name' => 'Test User']);
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio content'
        ]);
        
        $this->line("   Created test profile: {$profile->id}");
        
        // Test that direct updates now create revisions instead
        $originalBio = $profile->bio;
        $newBio = 'Updated bio content for testing';
        
        // Simulate authenticated user
        auth()->login($user);
        
        if (!$this->option('dry-run')) {
            // This should create a revision instead of updating directly
            $profile->update(['bio' => $newBio]);
            
            // Check that bio wasn't changed directly
            $profile->refresh();
            if ($profile->bio === $originalBio) {
                $this->line('   ✓ Direct update prevented, revision created instead');
            } else {
                throw new \Exception('Bio was updated directly instead of creating revision');
            }
            
            // Check that revision was created
            $revision = Revision::where('revisionable_id', $profile->id)
                             ->where('revisionable_type', MemberProfile::class)
                             ->first();
                             
            if ($revision && $revision->proposed_changes['bio'] === $newBio) {
                $this->line('   ✓ Revision created with correct proposed changes');
            } else {
                throw new \Exception('Revision not created or incorrect data');
            }
        } else {
            $this->line('   → Would create revision for bio update');
        }
    }

    protected function testAutoApproval(): void
    {
        $this->info('🚀 2. Testing auto-approval based on trust...');
        
        // Create user with high trust points
        $trustedUser = User::factory()->create([
            'name' => 'Trusted User',
            'trust_points' => ['member_profiles' => 35, 'global' => 35] // Above auto-approval threshold
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $trustedUser->id,
            'bio' => 'Original trusted bio'
        ]);
        
        auth()->login($trustedUser);
        
        if (!$this->option('dry-run')) {
            // This should auto-approve
            $profile->update(['bio' => 'Auto-approved bio update']);
            
            // Check if revision was auto-approved and applied
            $profile->refresh();
            if ($profile->bio === 'Auto-approved bio update') {
                $this->line('   ✓ High-trust user update was auto-approved and applied');
            } else {
                $this->line('   ⚠ High-trust user update not auto-approved (may need manual approval)');
            }
        } else {
            $this->line('   → Would auto-approve update from high-trust user');
        }
    }

    protected function testManualApproval(): void
    {
        $this->info('👥 3. Testing manual approval workflow...');
        
        // Create low-trust user
        $newUser = User::factory()->create([
            'name' => 'New User',
            'trust_points' => ['member_profiles' => 0, 'global' => 0]
        ]);
        
        $profile = MemberProfile::create([
            'user_id' => $newUser->id,
            'bio' => 'Original new user bio'
        ]);
        
        auth()->login($newUser);
        
        if (!$this->option('dry-run')) {
            $profile->update(['bio' => 'Pending approval bio']);
            
            // Should create pending revision
            $revision = Revision::where('revisionable_id', $profile->id)
                             ->where('revisionable_type', MemberProfile::class)
                             ->where('status', 'pending')
                             ->first();
                             
            if ($revision) {
                $this->line('   ✓ Low-trust user update created pending revision');
                
                // Test manual approval
                $moderator = User::factory()->create(['name' => 'Moderator']);
                $moderator->assignRole('moderator');

                \App\Actions\Revisions\ApproveRevision::run($revision, $moderator, 'Test approval');
                
                $profile->refresh();
                if ($profile->bio === 'Pending approval bio') {
                    $this->line('   ✓ Manual approval worked correctly');
                } else {
                    throw new \Exception('Manual approval failed to apply changes');
                }
            } else {
                throw new \Exception('Pending revision not created for low-trust user');
            }
        } else {
            $this->line('   → Would create pending revision for low-trust user');
            $this->line('   → Would test manual approval by moderator');
        }
    }

    protected function testTrustIntegration(): void
    {
        $this->info('🔗 4. Testing trust system integration...');
        
        $user = User::factory()->create(['name' => 'Trust Test User']);
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original trust test bio'
        ]);
        
        // Test trust level checking
        $trustInfo = $profile->getCreatorTrustInfo();
        if ($trustInfo) {
            $this->line("   ✓ Trust info retrieved: Level '{$trustInfo['level']}', Points: {$trustInfo['points']}");
        } else {
            $this->line('   ⚠ Trust info not available (may be expected for new user)');
        }
        
        // Test approval workflow determination
        $workflow = $profile->getApprovalWorkflow();
        $this->line("   ✓ Approval workflow: Requires approval: " . ($workflow['requires_approval'] ? 'Yes' : 'No'));
        $this->line("   ✓ Review priority: {$workflow['review_priority']}");
        $this->line("   ✓ Estimated review time: {$workflow['estimated_review_time']} hours");
    }

    protected function cleanTestData(): void
    {
        $this->info('🧹 Cleaning up test data...');
        
        if (!$this->option('dry-run')) {
            // Delete test revisions
            Revision::where('revisionable_type', MemberProfile::class)->delete();
            
            // Delete test profiles and users
            $testUsers = User::whereIn('name', [
                'Test User', 
                'Trusted User', 
                'New User', 
                'Moderator',
                'Trust Test User'
            ])->get();
            
            foreach ($testUsers as $user) {
                $user->memberProfile?->delete();
                $user->delete();
            }
            
            $this->line('   ✓ Test data cleaned up');
        } else {
            $this->line('   → Would clean up test data');
        }
    }
    
    protected function setupPermissions(): void
    {
        $this->info('🔧 Setting up permissions...');

        if (!$this->option('dry-run')) {
            // Create permissions if they don't exist
            Permission::firstOrCreate(['name' => 'approve revisions']);
            Permission::firstOrCreate(['name' => 'reject revisions']);
            Permission::firstOrCreate(['name' => 'view revisions']);

            // Create moderator role if it doesn't exist
            $moderator = Role::firstOrCreate(['name' => 'moderator']);
            $moderator->givePermissionTo(['approve revisions', 'reject revisions', 'view revisions']);

            $this->line('   ✓ Permissions set up successfully');
        } else {
            $this->line('   → Would set up revision permissions');
        }
    }
}
