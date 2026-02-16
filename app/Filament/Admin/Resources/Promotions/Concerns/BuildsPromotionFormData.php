<?php

namespace App\Filament\Admin\Resources\Promotions\Concerns;

use App\Enums\QualificationContext;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\Promotion;
use App\Models\Qualification;
use App\Models\SimpleDiscount;

trait BuildsPromotionFormData
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function createSimpleDiscount(array $data): SimpleDiscount
    {
        $kind = SimpleDiscountKind::from($data["discount_kind"]);

        $discountData = ["kind" => $kind->value];

        if ($kind === SimpleDiscountKind::PercentageOff) {
            $discountData["percentage"] = $data["discount_percentage"];
        } else {
            $discountData["amount"] = $this->parseAmountToMinor(
                $data["discount_amount"],
            );
            $discountData["amount_currency"] = "GBP";
        }

        return SimpleDiscount::query()->create($discountData);
    }

    /**
     * @param  array<int|string, mixed>  $rules
     */
    private function createQualificationRules(
        Qualification $root,
        array $rules,
        Promotion $promotion,
    ): void {
        $rules = array_values($rules);

        foreach ($rules as $sortOrder => $ruleData) {
            if ($ruleData["kind"] === QualificationRuleKind::Group->value) {
                $groupQualification = $promotion->qualifications()->create([
                    "parent_qualification_id" => $root->id,
                    "context" => QualificationContext::Group->value,
                    "op" => $ruleData["group_op"],
                    "sort_order" => $sortOrder,
                ]);

                $root->rules()->create([
                    "kind" => QualificationRuleKind::Group->value,
                    "group_qualification_id" => $groupQualification->id,
                    "sort_order" => $sortOrder,
                ]);

                $groupRules = array_values($ruleData["group_rules"] ?? []);

                foreach ($groupRules as $subSortOrder => $subRuleData) {
                    $subRule = $groupQualification->rules()->create([
                        "kind" => $subRuleData["kind"],
                        "sort_order" => $subSortOrder,
                    ]);

                    $subRule->syncTags(
                        array_values(
                            array_filter(
                                $subRuleData["tags"] ?? [],
                                "is_string",
                            ),
                        ),
                    );
                }
            } else {
                $rule = $root->rules()->create([
                    "kind" => $ruleData["kind"],
                    "sort_order" => $sortOrder,
                ]);

                $rule->syncTags(
                    array_values(
                        array_filter($ruleData["tags"] ?? [], "is_string"),
                    ),
                );
            }
        }
    }

    private function parseMonetaryBudget(mixed $value): ?int
    {
        if ($value === null || $value === "") {
            return null;
        }

        $float = (float) $value;

        if ($float === 0.0) {
            return null;
        }

        return (int) round($float * 100);
    }

    private function parseAmountToMinor(mixed $value): ?int
    {
        if ($value === null || $value === "") {
            return null;
        }

        $float = (float) $value;

        if ($float === 0.0) {
            return null;
        }

        return (int) round($float * 100);
    }
}
