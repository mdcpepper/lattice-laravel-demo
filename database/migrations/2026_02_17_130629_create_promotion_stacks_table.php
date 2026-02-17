<?php

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
        Schema::create('promotion_stacks', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table
                ->foreignIdFor(Team::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('root_layer_reference')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'name'], 'promotion_stack_team_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_stacks');
    }
};
