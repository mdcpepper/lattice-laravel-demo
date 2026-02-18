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
        Schema::table('backtested_carts', function (Blueprint $table) {
            $table
                ->unsignedInteger('processing_time')
                ->default(0)
                ->after('customer_id');
            $table
                ->unsignedInteger('solve_time')
                ->default(0)
                ->after('processing_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtested_carts', function (Blueprint $table) {
            $table->dropColumn('processing_time');
            $table->dropColumn('solve_time');
        });
    }
};
