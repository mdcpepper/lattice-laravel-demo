<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtested_cart_items', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table
                ->foreignId('backtest_id')
                ->constrained('backtests')
                ->cascadeOnDelete();
            $table
                ->foreignId('backtested_cart_id')
                ->constrained('backtested_carts')
                ->cascadeOnDelete();
            $table
                ->foreignId('cart_item_id')
                ->constrained('cart_items')
                ->cascadeOnDelete();
            $table
                ->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('subtotal');
            $table->char('subtotal_currency', 3);
            $table->unsignedBigInteger('total');
            $table->char('total_currency', 3);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtested_cart_items');
    }
};
