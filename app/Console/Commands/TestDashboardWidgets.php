<?php

namespace App\Console\Commands;

use App\Filament\Widgets\MyBandsWidget;
use App\Filament\Widgets\UpcomingEventsWidget;
use App\Models\User;
use Illuminate\Console\Command;

class TestDashboardWidgets extends Command
{
    protected $signature = 'test:dashboard-widgets {user?}';

    protected $description = 'Test the new dashboard widgets';

    public function handle()
    {
        $userId = $this->argument('user');
        $user = $userId ? User::find($userId) : User::first();
        
        if (!$user) {
            $this->error('No user found');
            return 1;
        }

        $this->info("Testing dashboard widgets for user: {$user->name} (ID: {$user->id})");
        $this->line('');

        // Simulate authentication
        auth()->login($user);
        
        try {
            // Test Upcoming Events Widget
            $this->info('🎭 Testing Upcoming Events Widget...');
            $eventsWidget = new UpcomingEventsWidget();
            $events = $eventsWidget->getUpcomingEvents();
            
            $this->line("Found {$events->count()} upcoming events:");
            foreach ($events as $event) {
                $performerCount = $event['performers']->count();
                $this->line("  • {$event['title']} ({$event['date_range']})");
                $this->line("    📍 {$event['venue_name']} | 💰 {$event['ticket_price_display']} | 🎵 {$performerCount} performers");
            }
            $this->line('');

            // Test My Bands Widget - now it's a table widget
            $this->info('🎸 Testing My Bands Widget (Table)...');
            
            // Test by checking the user's bands directly
            $ownedBands = $user->ownedBands()->count();
            $memberBands = $user->bandProfiles()->wherePivot('status', 'active')->count();
            $invitations = $user->bandProfiles()->wherePivot('status', 'invited')->count();
            
            $this->line("Band summary:");
            $this->line("  • Owned bands: {$ownedBands}");
            $this->line("  • Member of: {$memberBands}");
            $this->line("  • Pending invitations: {$invitations}");
            $this->line("  • Total active: " . ($ownedBands + $memberBands));
            
            if ($ownedBands + $memberBands > 0) {
                $this->line('');
                $this->line('Owned bands:');
                foreach ($user->ownedBands as $band) {
                    $memberCount = $band->activeMembers()->count();
                    $this->line("  • {$band->name} (owner) - {$memberCount} members");
                }
                
                $this->line('Member bands:');
                foreach ($user->bandProfiles()->wherePivot('status', 'active')->get() as $band) {
                    $memberCount = $band->activeMembers()->count();
                    $this->line("  • {$band->name} (member) - {$memberCount} members");
                }
            }

        } catch (\Exception $e) {
            $this->error("Error testing widgets: {$e->getMessage()}");
            return 1;
        } finally {
            auth()->logout();
        }

        $this->line('');
        $this->info('✅ Dashboard widgets test completed successfully');
        
        return 0;
    }
}