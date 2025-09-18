<?php

namespace Tests\Unit\Models;

use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    private Transaction $transaction;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'test@example.com']);
        $this->transaction = Transaction::factory()->create([
            'email' => 'test@example.com',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_belongs_to_user_via_email()
    {
        $userRelation = $this->transaction->user();

        $this->assertEquals('email', $userRelation->getOwnerKeyName());
        $this->assertEquals('email', $userRelation->getForeignKeyName());
        $this->assertEquals($this->user->id, $this->transaction->user->id);
    }

    #[Test]
    public function it_returns_null_when_no_matching_user_email()
    {
        $transaction = Transaction::factory()->create(['email' => 'nonexistent@example.com']);

        $this->assertNull($transaction->user);
    }

    #[Test]
    public function it_has_polymorphic_transactionable_relationship()
    {
        $reservation = Reservation::factory()->create();

        $transaction = Transaction::factory()->create([
            'transactionable_type' => Reservation::class,
            'transactionable_id' => $reservation->id,
        ]);

        $this->assertInstanceOf(Reservation::class, $transaction->transactionable);
        $this->assertEquals($reservation->id, $transaction->transactionable->id);
    }

    #[Test]
    public function it_returns_null_for_empty_transactionable()
    {
        $transaction = Transaction::factory()->create([
            'transactionable_type' => null,
            'transactionable_id' => null,
        ]);

        $this->assertNull($transaction->transactionable);
    }

    #[Test]
    public function it_casts_response_as_array()
    {
        $responseData = [
            'donation_id' => 'zeffy_123',
            'campaign' => 'Annual Fundraiser',
            'additional_questions' => ['How did you hear about us?' => 'Social media']
        ];

        $transaction = Transaction::factory()->create([
            'response' => $responseData
        ]);

        $this->assertIsArray($transaction->response);
        $this->assertEquals($responseData, $transaction->response);
        $this->assertEquals('zeffy_123', $transaction->response['donation_id']);
    }

    #[Test]
    public function it_handles_empty_response()
    {
        $transaction = Transaction::factory()->create(['response' => []]);

        $this->assertIsArray($transaction->response);
        $this->assertEmpty($transaction->response);
    }

    #[Test]
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'transaction_id',
            'email',
            'amount',
            'currency',
            'type',
            'response',
            'user_id',
            'transactionable_type',
            'transactionable_id',
        ];

        $this->assertEquals($fillable, $this->transaction->getFillable());
    }

    #[Test]
    public function it_creates_transaction_with_factory()
    {
        $transaction = Transaction::factory()->create();

        $this->assertNotNull($transaction->id);
        $this->assertNotNull($transaction->transaction_id);
        $this->assertNotNull($transaction->email);
        $this->assertNotNull($transaction->amount);
        $this->assertNotNull($transaction->currency);
        $this->assertNotNull($transaction->type);
        $this->assertNotNull($transaction->created_at);
        $this->assertNull($transaction->updated_at); // UPDATED_AT = null
    }

    #[Test]
    public function it_doesnt_update_updated_at_timestamp()
    {
        $originalCreatedAt = $this->transaction->created_at;

        $this->transaction->update(['amount' => 50.00]);
        $this->transaction->refresh();

        $this->assertEquals($originalCreatedAt, $this->transaction->created_at);
        $this->assertNull($this->transaction->updated_at);
    }

    #[Test]
    public function it_stores_different_transaction_types()
    {
        $types = ['donation', 'recurring', 'purchase', 'membership'];

        foreach ($types as $type) {
            $transaction = Transaction::factory()->create(['type' => $type]);
            $this->assertEquals($type, $transaction->type);
        }
    }

    #[Test]
    public function it_stores_different_currencies()
    {
        $currencies = ['USD', 'CAD', 'EUR', 'GBP'];

        foreach ($currencies as $currency) {
            $transaction = Transaction::factory()->create(['currency' => $currency]);
            $this->assertEquals($currency, $transaction->currency);
        }
    }

    #[Test]
    public function it_stores_decimal_amounts_correctly()
    {
        $amounts = [10.00, 15.50, 99.99, 100.00, 999999.99];

        foreach ($amounts as $amount) {
            $transaction = Transaction::factory()->create(['amount' => $amount]);
            $this->assertEquals($amount, $transaction->amount->getAmount()->toFloat());
            $this->assertInstanceOf(\Brick\Money\Money::class, $transaction->amount);
        }
    }

    #[Test]
    public function it_requires_unique_transaction_id()
    {
        $transactionId = 'unique_zeffy_123';
        Transaction::factory()->create(['transaction_id' => $transactionId]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Transaction::factory()->create(['transaction_id' => $transactionId]);
    }

    #[Test]
    public function it_can_be_associated_with_reservation()
    {
        $reservation = Reservation::factory()->create();
        $transaction = Transaction::factory()->create();

        $transaction->update([
            'transactionable_type' => Reservation::class,
            'transactionable_id' => $reservation->id,
        ]);

        $this->assertInstanceOf(Reservation::class, $transaction->transactionable);
        $this->assertEquals($reservation->id, $transaction->transactionable->id);
    }

    #[Test]
    public function it_formats_email_consistently()
    {
        $emails = [
            'Test@Example.com',
            'USER@DOMAIN.COM',
            'mixed.Case@Email.net'
        ];

        foreach ($emails as $email) {
            $transaction = Transaction::factory()->create(['email' => $email]);
            // Note: Email normalization should happen in the controller, not the model
            $this->assertEquals($email, $transaction->email);
        }
    }
}
