<?php

namespace App\Filament\Admin\Resources\Promotions\Concerns;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\QualificationContext;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Enums\TieredThresholdDiscountKind;
use App\Models\MixAndMatchDiscount;
use App\Models\Promotion;
use App\Models\Qualification;
use App\Models\SimpleDiscount;
use App\Models\TieredThresholdDiscount;

trait BuildsPromotionFormData
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function createSimpleDiscount(array $data): SimpleDiscount
    {
        $kind = SimpleDiscountKind::from($data['discount_kind']);

        $discountData = ['kind' => $kind->value];

        if ($kind === SimpleDiscountKind::PercentageOff) {
            $discountData['percentage'] = $data['discount_percentage'];
        } else {
            $discountData['amount'] = $this->parseAmountToMinor(
                $data['discount_amount'],
            );
            $discountData['amount_currency'] = 'GBP';
        }

        return SimpleDiscount::query()->create($discountData);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createMixAndMatchDiscount(array $data): MixAndMatchDiscount
    {
        $kind = MixAndMatchDiscountKind::from($data['discount_kind']);

        $discountData = ['kind' => $kind->value];

        if (
            in_array($kind->value, MixAndMatchDiscountKind::percentageTypes())
        ) {
            $discountData['percentage'] = $data['discount_percentage'] ?? null;
            $discountData['amount'] = null;
            $discountData['amount_currency'] = null;
        } elseif (
            in_array($kind->value, MixAndMatchDiscountKind::amountTypes())
        ) {
            $amount = $this->parseAmountToMinor(
                $data['discount_amount'] ?? null,
            );

            $discountData['percentage'] = null;
            $discountData['amount'] = $amount;
            $discountData['amount_currency'] = $amount !== null ? 'GBP' : null;
        } else {
            $discountData['percentage'] = null;
            $discountData['amount'] = null;
            $discountData['amount_currency'] = null;
        }

        return MixAndMatchDiscount::query()->create($discountData);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTieredThresholdDiscount(
        array $data,
    ): TieredThresholdDiscount {
        $kind = TieredThresholdDiscountKind::from($data['discount_kind']);

        $discountData = ['kind' => $kind->value];

        if (
            in_array(
                $kind->value,
                TieredThresholdDiscountKind::percentageTypes(),
                true,
            )
        ) {
            $discountData['percentage'] = $data['discount_percentage'] ?? null;
            $discountData['amount'] = null;
            $discountData['amount_currency'] = null;
        } elseif (
            in_array(
                $kind->value,
                TieredThresholdDiscountKind::amountTypes(),
                true,
            )
        ) {
            $amount = $this->parseAmountToMinor(
                $data['discount_amount'] ?? null,
            );

            $discountData['percentage'] = null;
            $discountData['amount'] = $amount;
            $discountData['amount_currency'] = $amount !== null ? 'GBP' : null;
        } else {
            $discountData['percentage'] = null;
            $discountData['amount'] = null;
            $discountData['amount_currency'] = null;
        }

        return TieredThresholdDiscount::query()->create($discountData);
    }

    /**
     * @param  array<int|string, mixed>  $rules
     */
    private function createQualificationRules(
        Qualification $root,
        array $rules,
        Promotion $promotion,
    ): void {
        $rules = $this->normalizeRepeaterRows($rules);

        foreach ($rules as $sortOrder => $ruleData) {
            if (
                ! is_array($ruleData) ||
                ! isset($ruleData['kind']) ||
                ! is_string($ruleData['kind'])
            ) {
                continue;
            }

            if ($ruleData['kind'] === QualificationRuleKind::Group->value) {
                $groupQualification = $promotion->qualifications()->create([
                    'parent_qualification_id' => $root->id,
                    'context' => QualificationContext::Group->value,
                    'op' => $ruleData['group_op'],
                    'sort_order' => $sortOrder,
                ]);

                $root->rules()->create([
                    'kind' => QualificationRuleKind::Group->value,
                    'group_qualification_id' => $groupQualification->id,
                    'sort_order' => $sortOrder,
                ]);

                $groupRules = $this->normalizeRepeaterRows(
                    $ruleData['group_rules'] ?? [],
                );

                foreach ($groupRules as $subSortOrder => $subRuleData) {
                    if (
                        ! is_array($subRuleData) ||
                        ! isset($subRuleData['kind']) ||
                        ! is_string($subRuleData['kind'])
                    ) {
                        continue;
                    }

                    $subRule = $groupQualification->rules()->create([
                        'kind' => $subRuleData['kind'],
                        'sort_order' => $subSortOrder,
                    ]);

                    $subRule->syncTags(
                        array_values(
                            array_filter(
                                $subRuleData['tags'] ?? [],
                                'is_string',
                            ),
                        ),
                    );
                }
            } else {
                $rule = $root->rules()->create([
                    'kind' => $ruleData['kind'],
                    'sort_order' => $sortOrder,
                ]);

                $rule->syncTags(
                    array_values(
                        array_filter($ruleData['tags'] ?? [], 'is_string'),
                    ),
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRepeaterRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_key_exists('kind', $value)) {
            return [$value];
        }

        $rows = array_values($value);

        return array_values(
            array_filter($rows, fn (mixed $row): bool => is_array($row)),
        );
    }

    private function parseMonetaryBudget(mixed $value): ?int
    {
        if ($value === null || $value === '') {
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
        if ($value === null || $value === '') {
            return null;
        }

        $float = (float) $value;

        if ($float === 0.0) {
            return null;
        }

        return (int) round($float * 100);
    }
}
