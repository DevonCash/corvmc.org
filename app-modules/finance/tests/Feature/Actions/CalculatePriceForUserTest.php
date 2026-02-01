<?php

use CorvMC\Finance\Enums\CreditType;
use App\Models\User;
use CorvMC\Finance\Actions\Pricing\CalculatePriceForUser;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Data\PriceCalculationData;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

function createMockChargeable(User $user, float $hours): Chargeable&RehearsalReservation
{
    $reservation = new RehearsalReservation([
        'reserved_at' => now()->addDay()->setHour(14),
        'reserved_until' => now()->addDay()->setHour(14)->addHours($hours),
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
    ]);
    $reservation->setRelation('reservable', $user);

    return $reservation;
}

describe('CalculatePriceForUser Action', function () {
    it('calculates price for non-sustaining member without credits', function () {
        $user = User::factory()->create();

        $chargeable = createMockChargeable($user, 2.0);

        $result = CalculatePriceForUser::run($chargeable, $user);

        expect($result)->toBeInstanceOf(PriceCalculationData::class);
        expect($result->amount)->toBe(3000); // $30 for 2 hours
        expect($result->net_amount)->toBe(3000); // No credits applied
        expect($result->credits_applied)->toBe([]);
        expect($result->credits_eligible)->toBeFalse();
        expect($result->rate)->toBe(1500); // $15/hour
        expect($result->unit)->toBe('hour');
        expect($result->billable_units)->toBe(2.0);
    });

    it('applies free hour credits for sustaining member', function () {
        $user = User::factory()->sustainingMember()->create();

        // Give user 8 credit blocks (4 hours worth)
        $user->addCredit(8, CreditType::FreeHours, 'test', null, 'Test credits');

        $chargeable = createMockChargeable($user, 2.0);

        $result = CalculatePriceForUser::run($chargeable, $user);

        expect($result->amount)->toBe(3000); // Gross: $30 for 2 hours
        expect($result->net_amount)->toBe(0); // Fully covered by credits
        expect($result->credits_applied)->toBe(['free_hours' => 4]); // 4 blocks = 2 hours
        expect($result->credits_eligible)->toBeTrue();
    });

    it('applies partial credits when user has insufficient balance', function () {
        $user = User::factory()->sustainingMember()->create();

        // Give user only 2 credit blocks (1 hour worth)
        $user->addCredit(2, CreditType::FreeHours, 'test', null, 'Test credits');

        $chargeable = createMockChargeable($user, 2.0);

        $result = CalculatePriceForUser::run($chargeable, $user);

        expect($result->amount)->toBe(3000); // Gross: $30 for 2 hours
        expect($result->net_amount)->toBe(1500); // $15 remaining after 1 hour credit
        expect($result->credits_applied)->toBe(['free_hours' => 2]); // 2 blocks = 1 hour
        expect($result->credits_eligible)->toBeTrue();
    });

    it('does not apply credits exceeding the charge amount', function () {
        $user = User::factory()->sustainingMember()->create();

        // Give user 16 credit blocks (8 hours worth) - more than needed
        $user->addCredit(16, CreditType::FreeHours, 'test', null, 'Test credits');

        $chargeable = createMockChargeable($user, 1.0);

        $result = CalculatePriceForUser::run($chargeable, $user);

        expect($result->amount)->toBe(1500); // Gross: $15 for 1 hour
        expect($result->net_amount)->toBe(0); // Fully covered
        expect($result->credits_applied)->toBe(['free_hours' => 2]); // Only 2 blocks needed
        expect($result->credits_eligible)->toBeTrue();
    });

    it('calculates without credits using withoutCredits method', function () {
        $user = User::factory()->sustainingMember()->create();

        // Give user credits
        $user->addCredit(8, CreditType::FreeHours, 'test', null, 'Test credits');

        $chargeable = createMockChargeable($user, 2.0);

        $result = (new CalculatePriceForUser)->withoutCredits($chargeable);

        expect($result->amount)->toBe(3000);
        expect($result->net_amount)->toBe(3000); // No credits applied
        expect($result->credits_applied)->toBe([]);
        expect($result->credits_eligible)->toBeFalse();
    });

    it('provides money conversion methods', function () {
        $user = User::factory()->create();
        $chargeable = createMockChargeable($user, 2.0);

        $result = CalculatePriceForUser::run($chargeable, $user);

        $amountMoney = $result->getAmountAsMoney();
        expect($amountMoney->getAmount()->toFloat())->toBe(30.0);
        expect((string) $amountMoney->getCurrency())->toBe('USD');

        $netMoney = $result->getNetAmountAsMoney();
        expect($netMoney->getAmount()->toFloat())->toBe(30.0);
    });

    it('calculates credit savings correctly', function () {
        $user = User::factory()->sustainingMember()->create();
        // Give 2 blocks (1 hour = $15 credit value) for a 2 hour reservation ($30)
        $user->addCredit(2, CreditType::FreeHours, 'test', null, 'Test credits');

        $chargeable = createMockChargeable($user, 2.0);

        $result = CalculatePriceForUser::run($chargeable, $user);

        $savings = $result->getCreditSavings();
        expect($savings->getMinorAmount()->toInt())->toBe(1500); // $15 saved (1 hour worth)
    });

    it('indicates when payment is required', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(2, CreditType::FreeHours, 'test', null, 'Test credits');

        $chargeable = createMockChargeable($user, 2.0);

        $result = CalculatePriceForUser::run($chargeable, $user);

        expect($result->requiresPayment())->toBeTrue();
        expect($result->hasCreditsApplied())->toBeTrue();
        expect($result->getTotalCreditsApplied())->toBe(2);
    });

    it('indicates when no payment is required (fully covered)', function () {
        $user = User::factory()->sustainingMember()->create();
        $user->addCredit(8, CreditType::FreeHours, 'test', null, 'Test credits');

        $chargeable = createMockChargeable($user, 2.0);

        $result = CalculatePriceForUser::run($chargeable, $user);

        expect($result->requiresPayment())->toBeFalse();
    });
});
