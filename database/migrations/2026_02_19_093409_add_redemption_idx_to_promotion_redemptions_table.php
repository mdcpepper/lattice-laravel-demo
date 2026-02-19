<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_redemptions', function (Blueprint $table): void {
            $table
                ->unsignedInteger('redemption_idx')
                ->default(0)
                ->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_redemptions', function (Blueprint $table): void {
            $table->dropColumn('redemption_idx');
        });
    }
};
