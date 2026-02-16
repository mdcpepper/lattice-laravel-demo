<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("qualifications", function (Blueprint $table): void {
            $table->id();
            $table
                ->foreignId("promotion_id")
                ->constrained("promotions")
                ->cascadeOnDelete();
            $table->nullableMorphs("qualifiable");
            $table
                ->foreignId("parent_qualification_id")
                ->nullable()
                ->constrained("qualifications")
                ->cascadeOnDelete();
            $table->string("context", 32)->default("primary");
            $table->enum("op", ["and", "or"]);
            $table->unsignedInteger("sort_order")->default(0);
            $table->timestamps();

            $table->index(
                ["promotion_id", "parent_qualification_id", "sort_order"],
                "qualifications_tree_idx",
            );
            $table->index(
                ["qualifiable_type", "qualifiable_id", "context"],
                "qualifications_owner_idx",
            );
        });

        Schema::create("qualification_rules", function (
            Blueprint $table,
        ): void {
            $table->id();
            $table
                ->foreignId("qualification_id")
                ->constrained("qualifications")
                ->cascadeOnDelete();
            $table->enum("kind", ["has_all", "has_any", "has_none", "group"]);
            $table
                ->foreignId("group_qualification_id")
                ->nullable()
                ->constrained("qualifications")
                ->nullOnDelete();
            $table->unsignedInteger("sort_order")->default(0);
            $table->timestamps();

            $table->index(
                ["qualification_id", "sort_order"],
                "qualification_rule_order_idx",
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("qualifications");
    }
};
