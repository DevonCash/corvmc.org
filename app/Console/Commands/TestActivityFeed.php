<?php

namespace App\Console\Commands;

use App\Filament\Widgets\ActivityFeedWidget;
use App\Models\User;
use Illuminate\Console\Command;

class TestActivityFeed extends Command
{
    protected $signature = 'test:activity-feed {user?}';

    protected $description = 'Test activity feed authorization filtering';

    public function handle()
    {
        $userId = $this->argument('user');
        $user = $userId ? User::find($userId) : User::first();
        
        if (!$user) {
            $this->error('No user found');
            return 1;
        }

        $this->info("Testing activity feed for user: {$user->name} (ID: {$user->id})");
        
        // Simulate authentication
        auth()->login($user);
        
        // Create widget instance
        $widget = new ActivityFeedWidget();
        $activities = $widget->getActivities();
        
        $this->info("Found {$activities->count()} visible activities:");
        $this->line('');
        
        foreach ($activities as $activity) {
            $this->line("• {$activity['description']} ({$activity['created_at']->diffForHumans()})");
        }
        
        auth()->logout();
        
        return 0;
    }
}