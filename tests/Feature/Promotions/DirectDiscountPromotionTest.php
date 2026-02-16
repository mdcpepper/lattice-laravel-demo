<?php

namespace Tests\Feature\Promotions;

use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\DirectDiscountPromotion;
use App\Models\Promotion;
use App\Models\SimpleDiscount;
use Illuminate\Support\Facades\DB;

it("can be created and persisted", function (): void {
    DB::transaction(function () use (
        &$promotion,
        &$discount,
        &$direct,
        &$rootQualification,
        &$nestedGroup,
        &$hasAllRule,
        &$groupRule,
        &$hasAnyRule,
        &$hasNoneRule,
    ) {
        $discount = SimpleDiscount::query()->create([
            "kind" => SimpleDiscountKind::PercentageOff,
            "percentage" => 10.0,
        ]);

        $direct = DirectDiscountPromotion::query()->create([
            "simple_discount_id" => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            "name" => "10% Off Eligible Items",
            "budget_application_limit" => 100,
            "budget_monetary_limit_minor" => 5000_00,
            "budget_monetary_limit_currency" => "GBP",
            "promotionable_type" => $direct->getMorphClass(),
            "promotionable_id" => $direct->id,
        ]);

        // AND(
        //   has_all([eligible, member]),
        //   group(OR(
        //     has_any([vip, staff]),
        //     has_none([blocked])
        //   )),
        // )

        $rootQualification = $direct->qualification()->create([
            "promotion_id" => $promotion->id,
            "context" => "primary",
            "op" => QualificationOp::And,
            "sort_order" => 0,
        ]);

        $hasAllRule = $rootQualification->rules()->create([
            "kind" => QualificationRuleKind::HasAll,
            "sort_order" => 0,
        ]);

        $nestedGroup = $promotion->qualifications()->create([
            "parent_qualification_id" => $rootQualification->id,
            "context" => "group",
            "op" => QualificationOp::Or,
            "sort_order" => 0,
        ]);

        $groupRule = $rootQualification->rules()->create([
            "kind" => QualificationRuleKind::Group,
            "group_qualification_id" => $nestedGroup->id,
            "sort_order" => 1,
        ]);

        $hasAnyRule = $nestedGroup->rules()->create([
            "kind" => QualificationRuleKind::HasAny,
            "sort_order" => 0,
        ]);

        $hasNoneRule = $nestedGroup->rules()->create([
            "kind" => QualificationRuleKind::HasNone,
            "sort_order" => 1,
        ]);

        $hasAllRule->syncTags(["eligible", "member"]);
        $hasAnyRule->syncTags(["vip", "staff"]);
        $hasNoneRule->syncTags(["blocked"]);
    }, 3);

    expect($promotion->promotionable)
        ->toBeInstanceOf(DirectDiscountPromotion::class)
        ->and($promotion->promotionable->discount->id)
        ->toBe($discount->id);

    $this->assertDatabaseHas("simple_discounts", [
        "id" => $discount->id,
        "kind" => "percentage_off",
        "percentage" => 1000,
    ]);

    $this->assertDatabaseHas("qualifications", [
        "id" => $rootQualification->id,
        "promotion_id" => $promotion->id,
        "qualifiable_type" => $direct->getMorphClass(),
        "qualifiable_id" => $direct->id,
        "context" => "primary",
        "op" => "and",
    ]);

    $this->assertDatabaseHas("qualifications", [
        "id" => $nestedGroup->id,
        "promotion_id" => $promotion->id,
        "parent_qualification_id" => $rootQualification->id,
        "context" => "group",
        "op" => "or",
    ]);

    $this->assertDatabaseHas("qualification_rules", [
        "id" => $hasAllRule->id,
        "qualification_id" => $rootQualification->id,
        "kind" => "has_all",
    ]);

    $this->assertDatabaseHas("qualification_rules", [
        "id" => $groupRule->id,
        "qualification_id" => $rootQualification->id,
        "kind" => "group",
        "group_qualification_id" => $nestedGroup->id,
    ]);

    $this->assertDatabaseHas("qualification_rules", [
        "id" => $hasAnyRule->id,
        "qualification_id" => $nestedGroup->id,
        "kind" => "has_any",
    ]);

    $this->assertDatabaseHas("qualification_rules", [
        "id" => $hasNoneRule->id,
        "qualification_id" => $nestedGroup->id,
        "kind" => "has_none",
    ]);
});
