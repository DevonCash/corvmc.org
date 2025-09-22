<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Store the original user-selected base amount in minor units (cents)
            $table->integer('base_amount')->nullable()->after('quantity');
            
            // Store the total amount including fees in minor units (cents)
            $table->integer('total_amount')->nullable()->after('base_amount');
            
            // Currency for the amounts
            $table->string('currency', 3)->default('USD')->after('total_amount');
            
            // Whether the user opted to cover processing fees
            $table->boolean('covers_fees')->default(false)->after('currency');
            
            // Store metadata about the subscription for easier querying
            $table->json('metadata')->nullable()->after('covers_fees');
            
            // Add index for amount-based queries
            $table->index(['base_amount', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['base_amount', 'currency']);
            $table->dropColumn([
                'base_amount',
                'total_amount', 
                'currency',
                'covers_fees',
                'metadata'
            ]);
        });
    }
};
