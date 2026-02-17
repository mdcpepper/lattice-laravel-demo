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
        Schema::create('promotion_layers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table
                ->foreignId('promotion_stack_id')
                ->constrained('promotion_stacks')
                ->cascadeOnDelete();
            $table->string('reference');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table
                ->enum('output_mode', ['pass_through', 'split'])
                ->default('pass_through');
            $table
                ->enum('participating_output_mode', ['pass_through', 'layer'])
                ->nullable();
            $table
                ->enum('non_participating_output_mode', [
                    'pass_through',
                    'layer',
                ])
                ->nullable();
            $table
                ->foreignId('participating_output_layer_id')
                ->nullable()
                ->constrained('promotion_layers')
                ->nullOnDelete();
            $table
                ->foreignId('non_participating_output_layer_id')
                ->nullable()
                ->constrained('promotion_layers')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['promotion_stack_id', 'reference'],
                'promotion_layer_reference_unique',
            );
            $table->index(
                ['promotion_stack_id', 'sort_order'],
                'promotion_layer_order_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_layers');
    }
};
