<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table
                ->foreignId('promotion_stack_id')
                ->constrained('promotion_stacks')
                ->cascadeOnDelete();
            $table->unsignedInteger('total_carts');
            $table->unsignedInteger('processed_carts')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_runs');
    }
};
