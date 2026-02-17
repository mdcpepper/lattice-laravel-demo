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
        Schema::create('promotion_layer_promotion', function (
            Blueprint $table,
        ): void {
            $table->id();
            $table
                ->foreignId('promotion_layer_id')
                ->constrained('promotion_layers')
                ->cascadeOnDelete();
            $table
                ->foreignIdFor(\App\Models\Promotion::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['promotion_layer_id', 'promotion_id'],
                'promotion_layer_promotion_unique',
            );
            $table->index(
                ['promotion_layer_id', 'sort_order'],
                'promotion_layer_promotion_order_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_layer_promotion');
    }
};
