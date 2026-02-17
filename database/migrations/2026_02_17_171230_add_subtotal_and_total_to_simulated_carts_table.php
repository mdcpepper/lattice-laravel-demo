<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulated_carts', function (Blueprint $table): void {
            $table
                ->unsignedBigInteger('subtotal')
                ->default(0)
                ->after('customer_id');
            $table
                ->char('subtotal_currency', 3)
                ->default('GBP')
                ->after('subtotal');
            $table
                ->unsignedBigInteger('total')
                ->default(0)
                ->after('subtotal_currency');
            $table->char('total_currency', 3)->default('GBP')->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('simulated_carts', function (Blueprint $table): void {
            $table->dropColumn([
                'subtotal',
                'subtotal_currency',
                'total',
                'total_currency',
            ]);
        });
    }
};
