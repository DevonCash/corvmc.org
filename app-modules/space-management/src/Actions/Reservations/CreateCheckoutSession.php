<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Services\ReservationService;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use ReservationService::createCheckoutSession() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class CreateCheckoutSession
{
    use AsAction;

    /**
     * @deprecated Use ReservationService::createCheckoutSession() instead
     */
    public function handle(RehearsalReservation $reservation)
    {
        return app(ReservationService::class)->createCheckoutSession($reservation);
    }

    public static function filamentAction(): Action
    {
        return Action::make('pay_stripe')
            ->label('Pay Online')
            ->icon('tabler-credit-card')
            ->color('success')
            ->visible(fn (Reservation $record) => $record instanceof RehearsalReservation &&
                $record->requiresPayment() && ($record->reservable_id === Auth::id() || User::me()->can('manage reservations')))
            ->action(function (Reservation $record) {
                $session = static::run($record);

                return redirect($session->url);
            });
    }
}
