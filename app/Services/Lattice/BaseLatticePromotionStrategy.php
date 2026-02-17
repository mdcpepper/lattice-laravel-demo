<?php

declare(strict_types=1);

namespace App\Services\Lattice;

use App\Contracts\Discount as DiscountContract;
use App\Enums\MixAndMatchDiscountKind;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\MixAndMatchDiscount as MixAndMatchDiscountModel;
use App\Models\Promotion as PromotionModel;
use App\Models\Qualification as QualificationModel;
use App\Models\QualificationRule as QualificationRuleModel;
use App\Models\SimpleDiscount as SimpleDiscountModel;
use Illuminate\Support\Collection;
use Lattice\Discount\Percentage;
use Lattice\Discount\SimpleDiscount;
use Lattice\Money;
use Lattice\Promotions\Budget;
use Lattice\Promotions\MixAndMatch\Discount as LatticeMixAndMatchDiscount;
use Lattice\Qualification;
use Lattice\Qualification\BoolOp;
use Lattice\Qualification\Rule;
use RuntimeException;

abstract class BaseLatticePromotionStrategy implements LatticePromotionStrategy
{
    /**
     * @param  Collection<int, QualificationModel>  $qualificationIndex
     */
    protected function makeQualification(
        QualificationModel $qualification,
        Collection $qualificationIndex,
    ): Qualification {
        $rules = $qualification->rules->sortBy('sort_order')->values()->all();

        /** @var Rule[] $latticeRules */
        $latticeRules = array_map(
            fn (QualificationRuleModel $rule): Rule => $this->makeRule(
                $rule,
                $qualificationIndex,
            ),
            $rules,
        );

        return new Qualification(
            op: $this->mapQualificationOp($qualification->op),
            rules: $latticeRules,
        );
    }

    /**
     * @param  Collection<int, QualificationModel>  $qualificationIndex
     */
    protected function makeRule(
        QualificationRuleModel $rule,
        Collection $qualificationIndex,
    ): Rule {
        $kind =
            $rule->kind instanceof QualificationRuleKind
                ? $rule->kind
                : QualificationRuleKind::from((string) $rule->kind);

        return match ($kind) {
            QualificationRuleKind::HasAll => Rule::hasAll(
                $this->ruleTags($rule),
            ),
            QualificationRuleKind::HasAny => Rule::hasAny(
                $this->ruleTags($rule),
            ),
            QualificationRuleKind::HasNone => Rule::hasNone(
                $this->ruleTags($rule),
            ),
            QualificationRuleKind::Group => Rule::group(
                $this->makeQualification(
                    $this->resolveGroupedQualification(
                        $rule,
                        $qualificationIndex,
                    ),
                    $qualificationIndex,
                ),
            ),
        };
    }

    /**
     * @param  Collection<int, QualificationModel>  $qualificationIndex
     */
    protected function resolveGroupedQualification(
        QualificationRuleModel $rule,
        Collection $qualificationIndex,
    ): QualificationModel {
        $groupQualificationId = $rule->group_qualification_id;

        if (is_null($groupQualificationId)) {
            throw new RuntimeException(
                'Group qualification rule is missing group_qualification_id.',
            );
        }

        $groupQualification = $qualificationIndex->get(
            (int) $groupQualificationId,
        );

        if ($groupQualification instanceof QualificationModel) {
            return $groupQualification;
        }

        if (
            $rule->relationLoaded('groupQualification') &&
            $rule->groupQualification instanceof QualificationModel
        ) {
            return $rule->groupQualification;
        }

        throw new RuntimeException(
            sprintf(
                'Unable to resolve grouped qualification [%d].',
                $groupQualificationId,
            ),
        );
    }

    /**
     * @return string[]
     */
    protected function ruleTags(QualificationRuleModel $rule): array
    {
        return $rule->tags
            ->map(fn ($tag): string => (string) $tag->name)
            ->values()
            ->all();
    }

    protected function makeSimpleDiscount(
        SimpleDiscountModel $discount,
    ): SimpleDiscount {
        $kind =
            $discount->kind instanceof SimpleDiscountKind
                ? $discount->kind
                : SimpleDiscountKind::from((string) $discount->kind);

        return match ($kind) {
            SimpleDiscountKind::PercentageOff => SimpleDiscount::percentageOff(
                Percentage::fromDecimal($this->normalizedPercentage($discount)),
            ),
            SimpleDiscountKind::AmountOverride => SimpleDiscount::amountOverride(
                $this->discountAmount($discount),
            ),
            SimpleDiscountKind::AmountOff => SimpleDiscount::amountOff(
                $this->discountAmount($discount),
            ),
        };
    }

    protected function makeMixAndMatchDiscount(
        MixAndMatchDiscountModel $discount,
    ): LatticeMixAndMatchDiscount {
        $kind =
            $discount->kind instanceof MixAndMatchDiscountKind
                ? $discount->kind
                : MixAndMatchDiscountKind::from((string) $discount->kind);

        return match ($kind) {
            MixAndMatchDiscountKind::PercentageOffAllItems => LatticeMixAndMatchDiscount::percentageOffAllItems(
                Percentage::fromDecimal($this->normalizedPercentage($discount)),
            ),
            MixAndMatchDiscountKind::AmountOffEachItem => LatticeMixAndMatchDiscount::amountOffEachItem(
                $this->discountAmount($discount),
            ),
            MixAndMatchDiscountKind::OverrideEachItem => LatticeMixAndMatchDiscount::overrideEachItem(
                $this->discountAmount($discount),
            ),
            MixAndMatchDiscountKind::AmountOffTotal => LatticeMixAndMatchDiscount::amountOffTotal(
                $this->discountAmount($discount),
            ),
            MixAndMatchDiscountKind::OverrideTotal => LatticeMixAndMatchDiscount::overrideTotal(
                $this->discountAmount($discount),
            ),
            MixAndMatchDiscountKind::PercentageOffCheapest => LatticeMixAndMatchDiscount::percentageOffCheapest(
                Percentage::fromDecimal($this->normalizedPercentage($discount)),
            ),
            MixAndMatchDiscountKind::OverrideCheapest => LatticeMixAndMatchDiscount::overrideCheapest(
                $this->discountAmount($discount),
            ),
        };
    }

    protected function normalizedPercentage(DiscountContract $discount): float
    {
        $percentage = $discount->discountPercentage();

        if (is_null($percentage)) {
            throw new RuntimeException(
                'Percentage discount is missing percentage value.',
            );
        }

        return ((float) $percentage) / 100;
    }

    protected function discountAmount(DiscountContract $discount): Money
    {
        $amount = $discount->discountAmount();
        $currency = $discount->discountAmountCurrency();

        if (is_null($amount) || is_null($currency)) {
            throw new RuntimeException(
                'Amount discount is missing amount and/or amount_currency.',
            );
        }

        return new Money((int) $amount, (string) $currency);
    }

    protected function makeBudget(PromotionModel $promotion): Budget
    {
        $applicationBudget = $promotion->application_budget;
        $monetaryBudget = $promotion->getRawOriginal('monetary_budget');

        if (is_null($applicationBudget) && is_null($monetaryBudget)) {
            return Budget::unlimited();
        }

        if (! is_null($applicationBudget) && is_null($monetaryBudget)) {
            return Budget::withApplicationLimit((int) $applicationBudget);
        }

        if (is_null($applicationBudget) && ! is_null($monetaryBudget)) {
            return Budget::withMonetaryLimit(
                new Money((int) $monetaryBudget, $this->defaultCurrency()),
            );
        }

        return Budget::withBothLimits(
            (int) $applicationBudget,
            new Money((int) $monetaryBudget, $this->defaultCurrency()),
        );
    }

    protected function defaultCurrency(): string
    {
        return (string) config('money.defaultCurrency', 'GBP');
    }

    protected function mapQualificationOp(QualificationOp|string $op): BoolOp
    {
        $op = $op instanceof QualificationOp ? $op : QualificationOp::from($op);

        return match ($op) {
            QualificationOp::And => BoolOp::AndOp,
            QualificationOp::Or => BoolOp::OrOp,
        };
    }

    /**
     * @return Collection<int, QualificationModel>
     */
    protected function qualificationIndex(PromotionModel $promotion): Collection
    {
        return $promotion->qualifications
            ->keyBy('id')
            ->map(fn ($qualification): QualificationModel => $qualification);
    }

    protected function unsupportedPromotionableType(
        PromotionModel $promotion,
    ): RuntimeException {
        $promotionable = $promotion->relationLoaded('promotionable')
            ? $promotion->getRelation('promotionable')
            : null;

        return new RuntimeException(
            sprintf(
                'Unsupported promotionable type [%s].',
                $promotion->promotionable_type ??
                    get_debug_type($promotionable),
            ),
        );
    }
}
