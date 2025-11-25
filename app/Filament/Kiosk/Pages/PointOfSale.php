<?php

namespace App\Filament\Kiosk\Pages;

use App\Actions\Sales\ProcessCashSale;
use App\Enums\ProductCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class PointOfSale extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected string $view = 'filament.kiosk.pages.point-of-sale';

    protected static ?string $title = 'Point of Sale';

    protected static ?string $navigationLabel = 'POS';

    protected static ?int $navigationSort = 10;

    public int $currentStep = 1;

    public ?string $activeCategory = 'all';

    public array $cart = []; // ['product_id' => quantity]

    public ?int $customerId = null;

    public ?float $tenderedAmount = null;

    public ?Sale $completedSale = null;

    public function mount(): void
    {
        $this->activeCategory = 'all';
        $this->currentStep = 1;
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customerId')
                    ->label('Member (Optional)')
                    ->searchable()
                    ->getSearchResultsUsing(
                        fn (string $search): array =>
                        User::where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])
                            ->toArray()
                    )
                    ->getOptionLabelUsing(
                        fn ($value): ?string =>
                        User::find($value)?->name
                    ),

                Forms\Components\TextInput::make('tenderedAmount')
                    ->label('Amount Tendered')
                    ->prefix('$')
                    ->numeric()
                    ->step(0.01)
                    ->required()
                    ->live(onBlur: true),
            ]);
    }

    public function getProducts(): Collection
    {
        $query = Product::where('is_active', true);

        if ($this->activeCategory && $this->activeCategory !== 'all') {
            $query->where('category', $this->activeCategory);
        }

        return $query->orderBy('category')->orderBy('name')->get();
    }

    public function getCategories(): array
    {
        return [
            'all' => 'All',
            ProductCategory::Concession->value => 'Concessions',
            ProductCategory::Merchandise->value => 'Merchandise',
            ProductCategory::Other->value => 'Other',
        ];
    }

    public function setCategory(string $category): void
    {
        $this->activeCategory = $category;
    }

    public function addToCart(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            $this->cart[$productId] = 0;
        }

        $this->cart[$productId]++;
    }

    public function removeFromCart(int $productId): void
    {
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]--;

            if ($this->cart[$productId] <= 0) {
                unset($this->cart[$productId]);
            }
        }
    }

    public function deleteFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->customerId = null;
        $this->tenderedAmount = null;
    }

    public function getCartItems(): Collection
    {
        if (empty($this->cart)) {
            return collect();
        }

        $products = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');

        return collect($this->cart)->map(function ($quantity, $productId) use ($products) {
            $product = $products[$productId] ?? null;

            if (! $product) {
                return null;
            }

            return [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $product->price->multipliedBy($quantity),
            ];
        })->filter();
    }

    public function getCartTotal(): int
    {
        return $this->getCartItems()->sum(function ($item) {
            return $item['subtotal']->getMinorAmount()->toInt();
        });
    }

    public function getChangeDue(): int
    {
        if (! $this->tenderedAmount) {
            return 0;
        }

        $tenderedCents = (int) ($this->tenderedAmount * 100);

        return $tenderedCents - $this->getCartTotal();
    }

    public function continueToReview(): void
    {
        if (empty($this->cart)) {
            Notification::make()
                ->warning()
                ->title('Cart is empty')
                ->body('Add items to cart before proceeding.')
                ->send();

            return;
        }

        $this->currentStep = 2;
    }

    public function continueToPayment(): void
    {
        $this->currentStep = 3;
    }

    public function processPayment(): void
    {
        if (! $this->tenderedAmount) {
            Notification::make()
                ->warning()
                ->title('Amount Required')
                ->body('Please enter the amount tendered.')
                ->send();

            return;
        }

        $tenderedCents = (int) ($this->tenderedAmount * 100);

        if ($tenderedCents < $this->getCartTotal()) {
            Notification::make()
                ->danger()
                ->title('Insufficient Payment')
                ->body('Tendered amount is less than the total.')
                ->send();

            return;
        }

        try {
            $customer = null;
            if ($this->customerId) {
                $customer = User::find($this->customerId);
            }

            $sale = ProcessCashSale::run(
                $this->cart,
                $tenderedCents,
                $customer
            );

            $this->completedSale = $sale;
            $this->currentStep = 4;

            Notification::make()
                ->success()
                ->title('Sale Completed')
                ->body("Total: {$sale->total->formatTo('en_US')}")
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title('Payment Failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function backToStep(int $step): void
    {
        $this->currentStep = $step;
    }

    public function finishSale(): void
    {
        $this->cart = [];
        $this->customerId = null;
        $this->tenderedAmount = null;
        $this->completedSale = null;
        $this->currentStep = 1;

        $this->redirect(KioskDashboard::getUrl());
    }

    public function cancelSale(): void
    {
        $this->cart = [];
        $this->customerId = null;
        $this->tenderedAmount = null;
        $this->completedSale = null;
        $this->currentStep = 1;

        $this->redirect(KioskDashboard::getUrl());
    }
}
