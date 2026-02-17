<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_stacks', function (Blueprint $table): void {
            $table
                ->date('active_from')
                ->nullable()
                ->after('root_layer_reference');
            $table->date('active_to')->nullable()->after('active_from');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_stacks', function (Blueprint $table): void {
            $table->dropColumn(['active_from', 'active_to']);
        });
    }
};
