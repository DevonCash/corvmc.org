<?php

namespace App\Console\Commands;

use App\Filament\Resources\ActivityLogResource;
use App\Models\User;
use Illuminate\Console\Command;

class TestActivityLogViewer extends Command
{
    protected $signature = 'test:activity-log-viewer {user?}';

    protected $description = 'Test activity log viewer authorization and filtering';

    public function handle()
    {
        $userId = $this->argument('user');
        $user = $userId ? User::find($userId) : User::first();
        
        if (!$user) {
            $this->error('No user found');
            return 1;
        }

        $this->info("Testing activity log viewer for user: {$user->name} (ID: {$user->id})");
        
        // Check permissions
        $canView = $user->can('view activity log');
        $canViewAll = $user->can('view all activity logs');
        $canDelete = $user->can('delete activity log');
        
        $this->line("Permissions:");
        $this->line("- View activity log: " . ($canView ? '✅' : '❌'));
        $this->line("- View all activity logs: " . ($canViewAll ? '✅' : '❌'));
        $this->line("- Delete activity log: " . ($canDelete ? '✅' : '❌'));
        $this->line('');
        
        if (!$canView) {
            $this->error('User cannot view activity log - access would be denied');
            return 1;
        }
        
        // Simulate authentication
        auth()->login($user);
        
        // Test query authorization
        try {
            $query = ActivityLogResource::getEloquentQuery();
            $count = $query->count();
            
            $this->info("Found {$count} activities visible to this user");
            
            // Show first few activities
            $activities = $query->limit(5)->get();
            
            $this->line('Recent activities:');
            foreach ($activities as $activity) {
                $description = $activity->description ?: 'No description';
                $causerName = $activity->causer?->name ?? 'System';
                $subjectType = $activity->subject_type ? class_basename($activity->subject_type) : 'System';
                
                $this->line("• {$description} by {$causerName} ({$subjectType}) - {$activity->created_at->diffForHumans()}");
            }
            
        } catch (\Exception $e) {
            $this->error("Error querying activities: {$e->getMessage()}");
            return 1;
        } finally {
            auth()->logout();
        }
        
        $this->info('✅ Activity log viewer test completed successfully');
        
        return 0;
    }
}