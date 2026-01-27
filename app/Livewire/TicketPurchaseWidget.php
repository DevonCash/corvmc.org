<?php

namespace App\Livewire;

use App\Models\User;
use CorvMC\Events\Actions\Tickets\CreateTicketOrder;
use CorvMC\Events\Actions\Tickets\ProcessTicketCheckout;
use CorvMC\Events\Models\Event;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Mobile-optimized ticket purchase widget.
 *
 * Supports both authenticated members and guest checkout.
 *
 * @property \Filament\Schemas\Schema $form
 */
class TicketPurchaseWidget extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Event $event;

    public ?array $data = [];

    public int $quantity = 1;

    public bool $coversFees = false;

    public bool $isProcessing = false;

    public function mount(Event $event): void
    {
        $this->event = $event;

        /** @var User|null $user */
        $user = Auth::user();

        // Pre-fill form for authenticated users
        $this->form->fill([
            'name' => $user?->name ?? '',
            'email' => $user?->email ?? '',
            'quantity' => 1,
            'covers_fees' => false,
        ]);
    }

    public function form(Schema $form): Schema
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $form
            ->schema([
                Section::make('Your Information')
                    ->description($user ? 'Purchasing as '.$user->email : 'Enter your details below')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->disabled((bool) $user),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->disabled((bool) $user),
                    ])
                    ->visible(!$user),

                Section::make('Tickets')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Number of Tickets')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue($this->getMaxQuantity())
                            ->default(1)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state) {
                                $this->quantity = max(1, (int) $state);
                            }),

                        Checkbox::make('covers_fees')
                            ->label('Cover processing fees')
                            ->helperText('Help support CMC by covering Stripe processing fees')
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->coversFees = (bool) $state),
                    ]),
            ])
            ->statePath('data');
    }

    public function purchase(): void
    {
        if ($this->isProcessing) {
            return;
        }

        $this->isProcessing = true;

        try {
            // Validate form
            $validated = $this->form->getState();

            /** @var User|null $user */
            $user = Auth::user();

            // Create the ticket order
            $order = CreateTicketOrder::run(
                event: $this->event,
                quantity: (int) ($validated['quantity'] ?? 1),
                name: $validated['name'] ?? $user?->name,
                email: $validated['email'] ?? $user?->email,
                user: $user,
                coversFees: (bool) ($validated['covers_fees'] ?? false)
            );

            // Create checkout session and redirect
            $checkout = ProcessTicketCheckout::run($order);

            $this->redirect($checkout->url);
        } catch (\InvalidArgumentException $e) {
            $this->isProcessing = false;

            Notification::make()
                ->title('Unable to Purchase')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\RuntimeException $e) {
            $this->isProcessing = false;

            Notification::make()
                ->title('Tickets Unavailable')
                ->body($e->getMessage())
                ->warning()
                ->send();
        } catch (\Exception $e) {
            $this->isProcessing = false;

            Notification::make()
                ->title('Error')
                ->body('An error occurred. Please try again.')
                ->danger()
                ->send();

            report($e);
        }
    }

    /**
     * Get maximum quantity available for purchase.
     */
    public function getMaxQuantity(): int
    {
        $remaining = $this->event->getTicketsRemaining();
        $maxPerOrder = config('ticketing.max_tickets_per_order', 10);

        if ($remaining === null) {
            return $maxPerOrder;
        }

        return min($remaining, $maxPerOrder);
    }

    /**
     * Calculate fees for current selection.
     */
    public function calculateFees(): int
    {
        /** @var User|null $user */
        $user = Auth::user();

        $unitPrice = $this->event->getTicketPriceForUser($user)->getMinorAmount()->toInt();
        $subtotal = $unitPrice * $this->quantity;

        // Stripe fee: 2.9% + $0.30
        return (int) round($subtotal * 0.029) + 30;
    }

    /**
     * Calculate total for display.
     */
    public function getTotal(): int
    {
        /** @var User|null $user */
        $user = Auth::user();

        $unitPrice = $this->event->getTicketPriceForUser($user)->getMinorAmount()->toInt();
        $subtotal = $unitPrice * $this->quantity;

        if ($this->coversFees) {
            $subtotal += $this->calculateFees();
        }

        return $subtotal;
    }

    /**
     * Get unit price for current user.
     */
    public function getUnitPrice(): int
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $this->event->getTicketPriceForUser($user)->getMinorAmount()->toInt();
    }

    /**
     * Get base price (for showing discount).
     */
    public function getBasePrice(): int
    {
        return $this->event->getBaseTicketPrice()->getMinorAmount()->toInt();
    }

    /**
     * Check if current user gets a discount.
     */
    public function hasDiscount(): bool
    {
        return $this->getUnitPrice() < $this->getBasePrice();
    }

    /**
     * Get discount percentage.
     */
    public function getDiscountPercent(): int
    {
        return config('ticketing.sustaining_member_discount', 50);
    }

    public function render()
    {
        return view('livewire.ticket-purchase-widget');
    }
}
