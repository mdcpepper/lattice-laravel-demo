<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_redemptions', function (
            Blueprint $table,
        ): void {
            $table
                ->string('redeemable_type')
                ->nullable()
                ->after('promotion_stack_id');
            $table
                ->unsignedBigInteger('redeemable_id')
                ->nullable()
                ->after('redeemable_type');
            $table->index(['redeemable_type', 'redeemable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('promotion_redemptions', function (
            Blueprint $table,
        ): void {
            $table->dropIndex(['redeemable_type', 'redeemable_id']);
            $table->dropColumn(['redeemable_type', 'redeemable_id']);
        });
    }
};
