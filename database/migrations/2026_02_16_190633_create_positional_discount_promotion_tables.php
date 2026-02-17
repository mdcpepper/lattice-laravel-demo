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
        Schema::create('positional_discount_promotions', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->unsignedSmallInteger('size');
            $table
                ->foreignId('simple_discount_id')
                ->constrained('simple_discounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('positional_discount_positions', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table
                ->foreignId('positional_discount_promotion_id')
                ->constrained('positional_discount_promotions')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['positional_discount_promotion_id', 'sort_order'],
                'position_order_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positional_discount_promotions');
        Schema::dropIfExists('positional_discount_positions');
    }
};
