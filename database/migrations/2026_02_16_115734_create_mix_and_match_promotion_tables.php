<?php

use App\Enums\MixAndMatchDiscountKind;
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
        Schema::create('mix_and_match_discounts', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->enum('kind', [
                MixAndMatchDiscountKind::PercentageOffAllItems->value,
                MixAndMatchDiscountKind::AmountOffEachItem->value,
                MixAndMatchDiscountKind::OverrideEachItem->value,
                MixAndMatchDiscountKind::AmountOffTotal->value,
                MixAndMatchDiscountKind::OverrideTotal->value,
                MixAndMatchDiscountKind::PercentageOffCheapest->value,
                MixAndMatchDiscountKind::OverrideCheapest->value,
            ]);
            $table->unsignedSmallInteger('percentage')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->char('amount_currency', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('mix_and_match_slots', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table
                ->foreignId('mix_and_match_promotion_id')
                ->constrained('mix_and_match_promotions')
                ->cascadeOnDelete();
            $table->json('reference')->nullable();
            $table->unsignedInteger('min');
            $table->unsignedInteger('max')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['mix_and_match_promotion_id', 'sort_order'],
                'slot_order_idx',
            );
        });

        Schema::create('mix_and_match_promotions', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table
                ->foreignId('mix_and_match_discount_id')
                ->constrained('mix_and_match_discounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mix_and_match_discounts');
        Schema::dropIfExists('mix_and_match_slots');
        Schema::dropIfExists('mix_and_match_promotions');
    }
};
