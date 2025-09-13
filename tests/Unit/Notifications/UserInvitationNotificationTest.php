<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserInvitationNotificationTest extends TestCase
{
    private User $invitedUser;
    private User $inviter;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inviter = User::factory()->create();
        $this->invitedUser = User::factory()->create([
            'name' => 'John Musician',
            'email' => 'john@example.com'
        ]);
        $this->token = 'test-invitation-token-123';
    }

    #[Test]
    public function it_constructs_with_user_and_token()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);

        $this->assertEquals($this->invitedUser, $notification->invitedUser);
        $this->assertEquals($this->token, $notification->invitationToken);
        $this->assertEquals([], $notification->roles);
    }

    #[Test]
    public function it_constructs_with_user_token_and_roles()
    {
        $roles = ['manager', 'admin'];
        $notification = new UserInvitationNotification($this->invitedUser, $this->token, $roles);

        $this->assertEquals($this->invitedUser, $notification->invitedUser);
        $this->assertEquals($this->token, $notification->invitationToken);
        $this->assertEquals($roles, $notification->roles);
    }

    #[Test]
    public function it_sends_via_mail_and_database()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $channels = $notification->via($this->inviter);

        $this->assertEquals(['mail', 'database'], $channels);
    }

    #[Test]
    public function it_creates_mail_message_with_correct_subject()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $mailMessage = $notification->toMail($this->invitedUser);

        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals('Welcome to Corvallis Music Collective!', $mailMessage->subject);
    }

    #[Test]
    public function it_includes_default_member_text_when_no_roles()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $mailMessage = $notification->toMail($this->invitedUser);

        $this->assertStringContainsString('as a member', $mailMessage->introLines[0]);
    }

    #[Test]
    public function it_includes_roles_text_when_roles_provided()
    {
        $roles = ['manager', 'admin'];
        $notification = new UserInvitationNotification($this->invitedUser, $this->token, $roles);
        $mailMessage = $notification->toMail($this->invitedUser);

        $this->assertStringContainsString('with the role(s): manager, admin', $mailMessage->introLines[0]);
    }

    #[Test]
    public function it_includes_single_role_correctly()
    {
        $roles = ['manager'];
        $notification = new UserInvitationNotification($this->invitedUser, $this->token, $roles);
        $mailMessage = $notification->toMail($this->invitedUser);

        $this->assertStringContainsString('with the role(s): manager', $mailMessage->introLines[0]);
    }

    #[Test]
    public function it_includes_community_description_in_mail()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $mailMessage = $notification->toMail($this->invitedUser);

        $this->assertStringContainsString('The Corvallis Music Collective is a community-driven space', $mailMessage->introLines[1]);
    }

    #[Test]
    public function it_includes_action_button_with_accept_url()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $mailMessage = $notification->toMail($this->invitedUser);

        $this->assertEquals('Accept Invitation', $mailMessage->actionText);
        $this->assertStringContainsString('invitation/accept', $mailMessage->actionUrl);
        $this->assertStringContainsString($this->token, $mailMessage->actionUrl);
    }

    #[Test]
    public function it_includes_expiration_notice_in_mail()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $mailMessage = $notification->toMail($this->invitedUser);

        $allLines = implode(' ', array_merge($mailMessage->introLines, $mailMessage->outroLines));
        $this->assertStringContainsString('This invitation will expire in 7 days', $allLines);
    }

    #[Test]
    public function it_creates_database_notification_with_correct_structure()
    {
        $roles = ['manager', 'admin'];
        $notification = new UserInvitationNotification($this->invitedUser, $this->token, $roles);
        $databaseData = $notification->toDatabase($this->inviter);

        $this->assertEquals('Invitation to Join CMC', $databaseData['title']);
        $this->assertEquals('You have been invited to join the Corvallis Music Collective.', $databaseData['body']);
        $this->assertEquals('heroicon-o-paper-airplane', $databaseData['icon']);
        $this->assertEquals($this->invitedUser->id, $databaseData['user_id']);
        $this->assertEquals($roles, $databaseData['roles']);
        $this->assertEquals($this->token, $databaseData['token']);
    }

    #[Test]
    public function it_creates_database_notification_with_empty_roles()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $databaseData = $notification->toDatabase($this->inviter);

        $this->assertEquals([], $databaseData['roles']);
    }

    #[Test]
    public function it_creates_array_representation_with_user_data()
    {
        $roles = ['manager'];
        $notification = new UserInvitationNotification($this->invitedUser, $this->token, $roles);
        $arrayData = $notification->toArray($this->inviter);

        $this->assertEquals($this->invitedUser->id, $arrayData['user_id']);
        $this->assertEquals($roles, $arrayData['roles']);
        $this->assertEquals($this->token, $arrayData['token']);
    }

    #[Test]
    public function it_is_queueable()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $traits = class_uses($notification);
        $this->assertContains('Illuminate\\Bus\\Queueable', $traits);
    }

    #[Test]
    public function it_should_queue()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $this->assertInstanceOf('Illuminate\Contracts\Queue\ShouldQueue', $notification);
    }

    #[Test]
    public function it_handles_multiple_roles()
    {
        $roles = ['manager', 'admin', 'moderator', 'sustaining member'];
        $notification = new UserInvitationNotification($this->invitedUser, $this->token, $roles);
        $mailMessage = $notification->toMail($this->invitedUser);

        $this->assertStringContainsString('manager, admin, moderator, sustaining member', $mailMessage->introLines[0]);
    }

    #[Test]
    public function it_generates_proper_accept_url_route()
    {
        $notification = new UserInvitationNotification($this->invitedUser, $this->token);
        $mailMessage = $notification->toMail($this->invitedUser);

        // The URL should match the route format
        $expectedUrl = route('invitation.accept', ['token' => $this->token]);
        $this->assertEquals($expectedUrl, $mailMessage->actionUrl);
    }
}
