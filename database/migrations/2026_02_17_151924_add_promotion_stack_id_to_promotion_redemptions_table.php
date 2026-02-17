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
        Schema::table('promotion_redemptions', function (Blueprint $table) {
            $table->foreignId('promotion_stack_id')->after('promotion_id')->constrained('promotion_stacks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_redemptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_stack_id');
        });
    }
};
