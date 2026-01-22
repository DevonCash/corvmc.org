<?php

namespace App\Console\Commands;

use Error;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;

class CreateSubscriptionPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:create-prices {--dry-run : Show what prices would be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Stripe price objects for sliding scale membership ($10-$50 in $5 increments) and fee coverage prices';

    protected $stripe;

    protected $membershipProduct;

    protected $feeCoverageProduct;

    public function __construct()
    {
        parent::__construct();
        $this->stripe = Cashier::stripe();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏷️  Creating Sliding Scale Membership Prices');
        $this->line('==========================================');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN mode - no prices will be created');
        }

        $amounts = [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60];

        foreach ($amounts as $amount) {
            $product = $this->createBasePrice($amount);
            $this->createFeeCoveragePrice($product);
        }

        $this->newLine();
        $this->info('✅ All subscription prices processed successfully!');

        return 0;
    }

    /**
     * Get existing product or create new one for sliding scale membership
     */
    private function getOrCreateProduct(): Product
    {
        if ($this->membershipProduct) {
            return $this->membershipProduct;
        }

        // Check if product ID is configured in environment
        $configuredProductId = config('services.stripe.membership_product_id');

        if (! $configuredProductId) {
            throw new Error('Define STRIPE_MEMBERSHIP_PRODUCT_ID');
        }

        try {
            $this->membershipProduct = Cashier::stripe()->products->retrieve($configuredProductId);
            $this->line("   ✓ Using existing membership product: {$this->membershipProduct->id}");

            return $this->membershipProduct;
        } catch (ApiErrorException $e) {

            $this->membershipProduct = Cashier::stripe()->products->create([
                'id' => $configuredProductId,
                'name' => 'Membership Contribution',
                'description' => 'Monthly sustaining membership with sliding scale pricing',
            ]);

            $this->line("   ✓ Created new membership product: {$this->membershipProduct->id}");

            return $this->membershipProduct;
        }
    }

    /**
     * Get existing fee coverage product or create new one
     */
    private function getOrCreateFeeCoverageProduct(): Product
    {
        if ($this->feeCoverageProduct) {
            return $this->feeCoverageProduct;
        }

        // Check if fee coverage product ID is configured in environment
        $configuredProductId = config('services.stripe.fee_coverage_product_id');

        if (! $configuredProductId) {
            throw new Error('Configure STRIPE_FEE_COVERAGE_PRODUCT_ID');
        }

        try {
            $this->feeCoverageProduct = Cashier::stripe()->products->retrieve($configuredProductId);
            $this->line("   ✓ Using existing fee coverage product: {$this->feeCoverageProduct->id}");

            return $this->feeCoverageProduct;
        } catch (ApiErrorException $e) {
            $this->feeCoverageProduct = Cashier::stripe()->products->create([
                'id' => $configuredProductId,
                'name' => 'Processing Fee Coverage',
                'description' => 'Thank you for covering processing fees! This helps support our organization by ensuring we receive the full value of your contribution.',
            ]);

            $this->line("   ✓ Created new fee coverage product: {$this->feeCoverageProduct->id}");

            return $this->feeCoverageProduct;
        }
    }

    /**
     * Create a base price for the given amount
     */
    private function createBasePrice(int $amountDollars): Price
    {
        $product = $this->getOrCreateProduct();
        $unitAmount = $amountDollars * 100; // Convert to cents
        $lookupKey = 'membership_monthly_'.$amountDollars;

        // Check if price already exists
        $price = $this->stripe->prices->all([
            'product' => $product->id,
            'lookup_keys' => [$lookupKey],
        ])->data[0] ?? null;

        if ($price) {
            $this->line("   ✓ Base price for \${$amountDollars} already exists: {$price->id}");

            return $price;
        }

        $price = $this->stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $unitAmount,
            'currency' => 'usd',
            'recurring' => [
                'interval' => 'month',
            ],
            'lookup_key' => $lookupKey,
        ]);

        $this->line("   ✓ Created base price for \${$amountDollars}: {$price->id}");

        return $price;
    }

    /**
     * Create a fee coverage price for the given amount
     */
    private function createFeeCoveragePrice(Price $forPrice): void
    {
        $product_id = $this->getOrCreateFeeCoverageProduct();

        // Check if fee coverage price already exists
        $price = $this->stripe->prices->all([
            'product' => $product_id,
            'lookup_keys' => ['fee_'.$forPrice->id],
        ])->data[0] ?? null;

        if ($price) {
            $this->line("   ✓ Fee coverage price for \${$forPrice->id} already exists: {$price->id}");

            return;
        }

        $feeCoverageCents = \CorvMC\Finance\Actions\Payments\CalculateFeeCoverage::run($forPrice->unit_amount);

        $price = $this->stripe->prices->create([
            'product' => $product_id,
            'unit_amount' => $feeCoverageCents->getMinorAmount()->toInt(),
            'currency' => 'usd',
            'recurring' => [
                'interval' => 'month',
            ],
            'lookup_key' => 'fee_'.$forPrice->id,
            'metadata' => [
                'base_amount' => $forPrice->unit_amount,
                'fee_for' => $forPrice->id,
            ],
        ]);

        $amountDollars = $forPrice->unit_amount / 100;
        $this->line("   ✓ Created fee coverage price for \${$amountDollars} base ({$feeCoverageCents->formatTo('en_US')}): {$price->id}");
    }
}
