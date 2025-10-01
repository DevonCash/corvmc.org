<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;

class CreatePracticeSpacePrice extends Command
{
    protected $signature = 'practice-space:create-price {--dry-run : Show what would be created}';

    protected $description = 'Create Stripe price object for practice space blocks ($7.50 per 30-minute block)';

    protected $stripe;

    public function __construct()
    {
        parent::__construct();
        $this->stripe = Cashier::stripe();
    }

    public function handle(): int
    {
        $this->info('🏷️  Creating Practice Space Pricing');
        $this->line('===================================');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN mode - no changes will be made');
            $this->line('');
            $this->line('Would create:');
            $this->line('  Product: Practice Space Reservation');
            $this->line('  Price: $7.50 per block (30 minutes)');
            $this->line('  Currency: USD');
            $this->line('  Type: One-time payment');
            return 0;
        }

        try {
            $product = $this->getOrCreateProduct();
            $price = $this->createPrice($product);

            $this->newLine();
            $this->info('✅ Practice space pricing created successfully!');
            $this->line('');
            $this->line('Add this to your .env file:');
            $this->warn("STRIPE_PRACTICE_SPACE_PRICE_ID={$price->id}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create pricing: ' . $e->getMessage());
            return 1;
        }
    }

    private function getOrCreateProduct(): Product
    {
        $configuredProductId = config('services.stripe.practice_space_product_id');

        if ($configuredProductId) {
            try {
                $product = $this->stripe->products->retrieve($configuredProductId);
                $this->line("   ✓ Using existing product: {$product->id}");
                return $product;
            } catch (ApiErrorException $e) {
                // Product not found, will create new one
            }
        }

        // Create new product with or without custom ID
        $productData = [
            'name' => 'Practice Space Reservation',
            'description' => 'Practice space rental by 30-minute blocks ($7.50 per block, $15/hour)',
        ];

        if ($configuredProductId) {
            $productData['id'] = $configuredProductId;
        }

        $product = $this->stripe->products->create($productData);
        $this->line("   ✓ Created new product: {$product->id}");

        return $product;
    }

    private function createPrice(Product $product): Price
    {
        $blockPriceCents = 750; // $7.50 per 30-minute block
        $lookupKey = 'practice_space_block';

        // Check if price already exists
        $existingPrices = $this->stripe->prices->all([
            'product' => $product->id,
            'lookup_keys' => [$lookupKey],
        ]);

        if (! empty($existingPrices->data)) {
            $price = $existingPrices->data[0];
            $this->line("   ✓ Price already exists: {$price->id}");
            return $price;
        }

        $price = $this->stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $blockPriceCents,
            'currency' => 'usd',
            'lookup_key' => $lookupKey,
            'metadata' => [
                'block_duration_minutes' => '30',
                'hourly_rate' => '$15.00',
            ],
        ]);

        $this->line("   ✓ Created price for \$7.50 per block: {$price->id}");

        return $price;
    }
}
