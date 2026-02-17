<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtested_carts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table
                ->foreignId('backtest_id')
                ->constrained('backtests')
                ->cascadeOnDelete();
            $table
                ->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();
            $table
                ->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->string('email')->nullable();
            $table
                ->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtested_carts');
    }
};
