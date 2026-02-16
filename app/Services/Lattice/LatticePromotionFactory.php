<?php

declare(strict_types=1);

namespace App\Services\Lattice;

use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\DirectDiscountPromotion as DirectDiscountPromotionModel;
use App\Models\Promotion as PromotionModel;
use App\Models\Qualification as QualificationModel;
use App\Models\QualificationRule as QualificationRuleModel;
use App\Models\SimpleDiscount as SimpleDiscountModel;
use Illuminate\Support\Collection;
use Lattice\Discount\Percentage;
use Lattice\Discount\SimpleDiscount;
use Lattice\Money;
use Lattice\Promotions\Budget;
use Lattice\Promotions\DirectDiscountPromotion;
use Lattice\Promotions\Promotion as LatticePromotion;
use Lattice\Qualification;
use Lattice\Qualification\BoolOp;
use Lattice\Qualification\Rule;
use RuntimeException;

class LatticePromotionFactory
{
    public function make(PromotionModel $promotion): LatticePromotion
    {
        $promotionable = $promotion->promotionable;

        return match (true) {
            $promotionable instanceof DirectDiscountPromotionModel
                => $this->makeDirectDiscountPromotion(
                $promotion,
                $promotionable,
            ),
            default => throw new RuntimeException(
                sprintf(
                    "Unsupported promotionable type [%s].",
                    $promotion->promotionable_type ??
                        get_debug_type($promotionable),
                ),
            ),
        };
    }

    private function makeDirectDiscountPromotion(
        PromotionModel $promotion,
        DirectDiscountPromotionModel $directPromotion,
    ): DirectDiscountPromotion {
        $discount = $directPromotion->discount;

        if (!$discount instanceof SimpleDiscountModel) {
            throw new RuntimeException(
                "Direct discount promotion is missing its simple discount relation.",
            );
        }

        /** @var Collection<int, QualificationModel> $qualificationIndex */
        $qualificationIndex = $promotion->qualifications
            ->keyBy("id")
            ->map(fn($qualification): QualificationModel => $qualification);

        $rootQualification = $this->resolveRootQualification(
            $promotion,
            $directPromotion,
        );

        return new DirectDiscountPromotion(
            reference: $promotion,
            qualification: $this->makeQualification(
                $rootQualification,
                $qualificationIndex,
            ),
            discount: $this->makeSimpleDiscount($discount),
            budget: $this->makeBudget($promotion),
        );
    }

    private function resolveRootQualification(
        PromotionModel $promotion,
        DirectDiscountPromotionModel $directPromotion,
    ): QualificationModel {
        $directQualification = $directPromotion->relationLoaded("qualification")
            ? $directPromotion->qualification
            : null;

        if ($directQualification instanceof QualificationModel) {
            return $directQualification;
        }

        $qualification = $promotion->qualifications->first(
            fn(QualificationModel $candidate): bool => $candidate->context ===
                "primary" &&
                $candidate->qualifiable_type ===
                    $directPromotion->getMorphClass() &&
                (int) $candidate->qualifiable_id ===
                    (int) $directPromotion->getKey(),
        );

        if ($qualification instanceof QualificationModel) {
            return $qualification;
        }

        throw new RuntimeException(
            "Direct discount promotion is missing its primary qualification.",
        );
    }

    /**
     * @param Collection<int, QualificationModel> $qualificationIndex
     */
    private function makeQualification(
        QualificationModel $qualification,
        Collection $qualificationIndex,
    ): Qualification {
        $rules = $qualification->rules->sortBy("sort_order")->values()->all();

        /** @var Rule[] $latticeRules */
        $latticeRules = array_map(
            fn(QualificationRuleModel $rule): Rule => $this->makeRule(
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
     * @param Collection<int, QualificationModel> $qualificationIndex
     */
    private function makeRule(
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
     * @param Collection<int, QualificationModel> $qualificationIndex
     */
    private function resolveGroupedQualification(
        QualificationRuleModel $rule,
        Collection $qualificationIndex,
    ): QualificationModel {
        $groupQualificationId = $rule->group_qualification_id;

        if (is_null($groupQualificationId)) {
            throw new RuntimeException(
                "Group qualification rule is missing group_qualification_id.",
            );
        }

        $groupQualification = $qualificationIndex->get(
            (int) $groupQualificationId,
        );

        if ($groupQualification instanceof QualificationModel) {
            return $groupQualification;
        }

        if (
            $rule->relationLoaded("groupQualification") &&
            $rule->groupQualification instanceof QualificationModel
        ) {
            return $rule->groupQualification;
        }

        throw new RuntimeException(
            sprintf(
                "Unable to resolve grouped qualification [%d].",
                $groupQualificationId,
            ),
        );
    }

    /**
     * @return string[]
     */
    private function ruleTags(QualificationRuleModel $rule): array
    {
        return $rule->tags
            ->map(fn($tag): string => (string) $tag->name)
            ->values()
            ->all();
    }

    private function makeSimpleDiscount(
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
            SimpleDiscountKind::AmountOverride
                => SimpleDiscount::amountOverride(
                $this->discountAmount($discount),
            ),
            SimpleDiscountKind::AmountOff => SimpleDiscount::amountOff(
                $this->discountAmount($discount),
            ),
        };
    }

    private function normalizedPercentage(SimpleDiscountModel $discount): float
    {
        $percentage = $discount->percentage;

        if (is_null($percentage)) {
            throw new RuntimeException(
                "Percentage discount is missing percentage value.",
            );
        }

        return ((float) $percentage) / 100;
    }

    private function discountAmount(SimpleDiscountModel $discount): Money
    {
        $amount = $discount->amount;
        $currency = $discount->amount_currency;

        if (is_null($amount) || is_null($currency)) {
            throw new RuntimeException(
                "Amount discount is missing amount and/or amount_currency.",
            );
        }

        return new Money((int) $amount, (string) $currency);
    }

    private function makeBudget(PromotionModel $promotion): Budget
    {
        $applicationBudget = $promotion->application_budget;
        $monetaryBudget = $promotion->getRawOriginal("monetary_budget");

        if (is_null($applicationBudget) && is_null($monetaryBudget)) {
            return Budget::unlimited();
        }

        if (!is_null($applicationBudget) && is_null($monetaryBudget)) {
            return Budget::withApplicationLimit((int) $applicationBudget);
        }

        if (is_null($applicationBudget) && !is_null($monetaryBudget)) {
            return Budget::withMonetaryLimit(
                new Money((int) $monetaryBudget, $this->defaultCurrency()),
            );
        }

        return Budget::withBothLimits(
            (int) $applicationBudget,
            new Money((int) $monetaryBudget, $this->defaultCurrency()),
        );
    }

    private function defaultCurrency(): string
    {
        return (string) config("money.defaultCurrency", "GBP");
    }

    private function mapQualificationOp(QualificationOp|string $op): BoolOp
    {
        $op = $op instanceof QualificationOp ? $op : QualificationOp::from($op);

        return match ($op) {
            QualificationOp::And => BoolOp::AndOp,
            QualificationOp::Or => BoolOp::OrOp,
        };
    }
}
