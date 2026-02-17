<?php

use App\Enums\SimpleDiscountKind;
use App\Models\Team;
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
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table
                ->foreignIdFor(Team::class)
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('name');
            $table->morphs('promotionable');
            $table->unsignedInteger('application_budget')->nullable();
            $table->unsignedInteger('monetary_budget')->nullable();
            $table->timestamps();
        });

        Schema::create('simple_discounts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->enum('kind', [
                SimpleDiscountKind::PercentageOff->value,
                SimpleDiscountKind::AmountOverride->value,
                SimpleDiscountKind::AmountOff->value,
            ]);
            $table->unsignedSmallInteger('percentage')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->char('amount_currency', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('direct_discount_promotions', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table
                ->foreignId('simple_discount_id')
                ->constrained('simple_discounts')
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
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('simple_discounts');
        Schema::dropIfExists('direct_discount_promotions');
    }
};
