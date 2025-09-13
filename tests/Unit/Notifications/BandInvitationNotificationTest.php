<?php

namespace Tests\Unit\Notifications;

use App\Models\Band;
use App\Models\User;
use App\Notifications\BandInvitationNotification;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BandInvitationNotificationTest extends TestCase
{
    private User $user;
    private Band $band;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->band = Band::factory()->create([
            'name' => 'Test Band',
            'bio' => 'We are a test band with great music.'
        ]);
    }

    #[Test]
    public function it_constructs_with_band_and_role()
    {
        $notification = new BandInvitationNotification($this->band, 'member');
        
        $this->assertEquals($this->band, $notification->band);
        $this->assertEquals('member', $notification->role);
        $this->assertNull($notification->position);
    }

    #[Test]
    public function it_constructs_with_band_role_and_position()
    {
        $notification = new BandInvitationNotification($this->band, 'member', 'guitarist');
        
        $this->assertEquals($this->band, $notification->band);
        $this->assertEquals('member', $notification->role);
        $this->assertEquals('guitarist', $notification->position);
    }

    #[Test]
    public function it_sends_via_mail_and_database()
    {
        $notification = new BandInvitationNotification($this->band, 'member');
        $channels = $notification->via($this->user);
        
        $this->assertEquals(['mail', 'database'], $channels);
    }

    #[Test]
    public function it_creates_mail_message_with_band_details()
    {
        $notification = new BandInvitationNotification($this->band, 'member');
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals("You're invited to join Test Band!", $mailMessage->subject);
        
        $this->assertStringContainsString("You've been invited to join Test Band", $mailMessage->introLines[0]);
        $this->assertStringContainsString("Here's a bit about the band:", $mailMessage->introLines[1]);
        $this->assertStringContainsString('We are a test band with great music.', $mailMessage->introLines[2]);
    }

    #[Test]
    public function it_includes_position_in_mail_when_provided()
    {
        $notification = new BandInvitationNotification($this->band, 'member', 'guitarist');
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertStringContainsString("join Test Band as guitarist", $mailMessage->introLines[0]);
    }

    #[Test]
    public function it_excludes_position_from_mail_when_not_provided()
    {
        $notification = new BandInvitationNotification($this->band, 'member');
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertStringContainsString("join Test Band.", $mailMessage->introLines[0]);
        $this->assertStringNotContainsString(" as ", $mailMessage->introLines[0]);
    }

    #[Test]
    public function it_handles_empty_band_bio_in_mail()
    {
        $bandWithoutBio = Band::factory()->create([
            'name' => 'Band Without Bio',
            'bio' => null
        ]);
        
        $notification = new BandInvitationNotification($bandWithoutBio, 'member');
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertStringContainsString('No bio available yet.', $mailMessage->introLines[2]);
    }

    #[Test]
    public function it_includes_action_button_to_view_band()
    {
        $notification = new BandInvitationNotification($this->band, 'member');
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertEquals('View Band Profile', $mailMessage->actionText);
        $this->assertStringContainsString('bands', $mailMessage->actionUrl);
    }

    #[Test]
    public function it_creates_database_notification_with_correct_structure()
    {
        $notification = new BandInvitationNotification($this->band, 'member', 'guitarist');
        $databaseData = $notification->toDatabase($this->user);
        
        $this->assertEquals('Band Invitation', $databaseData['title']);
        $this->assertEquals("You've been invited to join Test Band as guitarist", $databaseData['body']);
        $this->assertEquals('heroicon-o-user-group', $databaseData['icon']);
        $this->assertEquals($this->band->id, $databaseData['band_id']);
        $this->assertEquals('Test Band', $databaseData['band_name']);
        $this->assertEquals('member', $databaseData['role']);
        $this->assertEquals('guitarist', $databaseData['position']);
    }

    #[Test]
    public function it_creates_database_notification_without_position()
    {
        $notification = new BandInvitationNotification($this->band, 'member');
        $databaseData = $notification->toDatabase($this->user);
        
        $this->assertEquals("You've been invited to join Test Band", $databaseData['body']);
        $this->assertNull($databaseData['position']);
    }

    #[Test]
    public function it_creates_array_representation_with_band_data()
    {
        $notification = new BandInvitationNotification($this->band, 'member', 'guitarist');
        $arrayData = $notification->toArray($this->user);
        
        $this->assertEquals($this->band->id, $arrayData['band_id']);
        $this->assertEquals('Test Band', $arrayData['band_name']);
        $this->assertEquals('member', $arrayData['role']);
        $this->assertEquals('guitarist', $arrayData['position']);
    }

    #[Test]
    public function it_handles_different_roles()
    {
        $roles = ['member', 'admin', 'owner'];
        
        foreach ($roles as $role) {
            $notification = new BandInvitationNotification($this->band, $role);
            $databaseData = $notification->toDatabase($this->user);
            
            $this->assertEquals($role, $databaseData['role']);
        }
    }

    #[Test]
    public function it_is_queueable()
    {
        $notification = new BandInvitationNotification($this->band, 'member');
        $traits = class_uses($notification);
        $this->assertContains('Illuminate\\Bus\\Queueable', $traits);
    }

    #[Test]
    public function it_handles_long_band_names()
    {
        $longNameBand = Band::factory()->create([
            'name' => 'This is a Very Long Band Name That Should Still Work Properly in Notifications'
        ]);
        
        $notification = new BandInvitationNotification($longNameBand, 'member');
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertStringContainsString('This is a Very Long Band Name', $mailMessage->subject);
    }
}