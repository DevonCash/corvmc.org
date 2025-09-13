<?php

namespace Tests\Unit\Notifications;

use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationConfirmedNotification;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationConfirmedNotificationTest extends TestCase
{
    private User $user;
    private Reservation $reservation;
    private ReservationConfirmedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'reserved_at' => Carbon::parse('2024-01-15 14:00'),
            'reserved_until' => Carbon::parse('2024-01-15 16:00'),
            'hours_used' => 2.0,
            'cost' => 30.00,
            'notes' => 'Test reservation notes',
        ]);
        
        $this->notification = new ReservationConfirmedNotification($this->reservation);
    }

    #[Test]
    public function it_sends_via_mail_and_database()
    {
        $channels = $this->notification->via($this->user);
        
        $this->assertEquals(['mail', 'database'], $channels);
    }

    #[Test]
    public function it_creates_mail_message_with_reservation_details()
    {
        $mailMessage = $this->notification->toMail($this->user);
        
        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals('Practice Space Reservation Confirmed', $mailMessage->subject);
        
        // Check that mail contains reservation details
        $this->assertStringContainsString('Your practice space reservation has been confirmed!', $mailMessage->introLines[0]);
        $this->assertStringContainsString('**Reservation Details:**', $mailMessage->introLines[1]);
        $this->assertStringContainsString('Duration: 2.0 hours', $mailMessage->introLines[3]);
        $this->assertStringContainsString('Cost:', $mailMessage->introLines[4]);
    }

    #[Test]
    public function it_includes_notes_in_mail_when_present()
    {
        $mailMessage = $this->notification->toMail($this->user);
        
        $this->assertStringContainsString('**Notes:** Test reservation notes', 
            implode(' ', $mailMessage->introLines));
    }

    #[Test]
    public function it_excludes_notes_from_mail_when_empty()
    {
        $reservationWithoutNotes = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'notes' => null,
        ]);
        
        $notification = new ReservationConfirmedNotification($reservationWithoutNotes);
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertStringNotContainsString('**Notes:**', 
            implode(' ', $mailMessage->introLines));
    }

    #[Test]
    public function it_includes_payment_reminder_for_unpaid_reservations()
    {
        $unpaidReservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 30.00,
            'status' => 'pending',
        ]);
        
        $notification = new ReservationConfirmedNotification($unpaidReservation);
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertStringContainsString('**Payment Required:**', 
            implode(' ', $mailMessage->introLines));
    }

    #[Test]
    public function it_excludes_payment_reminder_for_free_reservations()
    {
        $freeReservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0,
            'status' => 'pending',
        ]);
        
        $notification = new ReservationConfirmedNotification($freeReservation);
        $mailMessage = $notification->toMail($this->user);
        
        $this->assertStringNotContainsString('**Payment Required:**', 
            implode(' ', $mailMessage->introLines));
    }

    #[Test]
    public function it_includes_action_button_to_view_reservation()
    {
        $mailMessage = $this->notification->toMail($this->user);
        
        $this->assertEquals('View Reservation', $mailMessage->actionText);
        $this->assertStringContainsString('reservations', $mailMessage->actionUrl);
    }

    #[Test]
    public function it_creates_database_notification_with_correct_structure()
    {
        $databaseData = $this->notification->toDatabase($this->user);
        
        $this->assertEquals('Reservation Confirmed', $databaseData['title']);
        $this->assertStringContainsString('has been confirmed', $databaseData['body']);
        $this->assertEquals('heroicon-o-check-circle', $databaseData['icon']);
        $this->assertEquals($this->reservation->id, $databaseData['reservation_id']);
        $this->assertEquals($this->reservation->reserved_at, $databaseData['reserved_at']);
        $this->assertEquals($this->reservation->duration, $databaseData['duration']);
        $this->assertEquals($this->reservation->cost, $databaseData['cost']);
    }

    #[Test]
    public function it_creates_array_representation_with_reservation_data()
    {
        $arrayData = $this->notification->toArray($this->user);
        
        $this->assertEquals($this->reservation->id, $arrayData['reservation_id']);
        $this->assertEquals($this->reservation->reserved_at, $arrayData['reserved_at']);
        $this->assertEquals($this->reservation->duration, $arrayData['duration']);
        $this->assertEquals($this->reservation->cost, $arrayData['cost']);
    }

    #[Test]
    public function it_is_queueable()
    {
        $traits = class_uses($this->notification);
        $this->assertContains('Illuminate\\Bus\\Queueable', $traits);
    }

    #[Test]
    public function it_should_queue()
    {
        $this->assertInstanceOf('Illuminate\Contracts\Queue\ShouldQueue', $this->notification);
    }

    #[Test]
    public function it_constructs_with_reservation()
    {
        $notification = new ReservationConfirmedNotification($this->reservation);
        
        $this->assertEquals($this->reservation, $notification->reservation);
    }

    #[Test]
    public function it_formats_reservation_time_in_database_notification()
    {
        $databaseData = $this->notification->toDatabase($this->user);
        
        // Check that the formatted date is included in the body
        $this->assertStringContainsString('Jan 15, 2024 2:00 PM', $databaseData['body']);
    }
}