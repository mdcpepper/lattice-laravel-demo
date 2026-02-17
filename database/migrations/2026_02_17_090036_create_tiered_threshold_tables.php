<?php

use App\Enums\TieredThresholdDiscountKind;
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
        Schema::create('tiered_threshold_discounts', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->enum('kind', [
                TieredThresholdDiscountKind::PercentageOffEachItem->value,
                TieredThresholdDiscountKind::PercentageOffCheapest->value,
                TieredThresholdDiscountKind::AmountOffTotal->value,
                TieredThresholdDiscountKind::AmountOffEachItem->value,
                TieredThresholdDiscountKind::OverrideTotal->value,
                TieredThresholdDiscountKind::OverrideEachItem->value,
                TieredThresholdDiscountKind::OverrideCheapest->value,
            ]);
            $table->unsignedSmallInteger('percentage')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->char('amount_currency', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('tiered_threshold_promotions', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->timestamps();
        });

        Schema::create('tiered_threshold_tiers', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table
                ->foreignId('tiered_threshold_promotion_id')
                ->constrained('tiered_threshold_promotions')
                ->cascadeOnDelete();
            $table
                ->foreignId('tiered_threshold_discount_id')
                ->constrained('tiered_threshold_discounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->bigInteger('lower_monetary_threshold_minor')->nullable();
            $table->char('lower_monetary_threshold_currency', 3)->nullable();
            $table->unsignedInteger('lower_item_count_threshold')->nullable();

            $table->bigInteger('upper_monetary_threshold_minor')->nullable();
            $table->char('upper_monetary_threshold_currency', 3)->nullable();
            $table->unsignedInteger('upper_item_count_threshold')->nullable();

            $table->timestamps();

            $table->index(
                ['tiered_threshold_promotion_id', 'sort_order'],
                'tier_order_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiered_threshold_tiers');
        Schema::dropIfExists('tiered_threshold_promotions');
        Schema::dropIfExists('tiered_threshold_discounts');
    }
};
