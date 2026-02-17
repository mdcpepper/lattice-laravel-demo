<?php

declare(strict_types=1);

namespace App\Services\Lattice;

use App\Enums\QualificationContext;
use App\Models\DirectDiscountPromotion as DirectDiscountPromotionModel;
use App\Models\Promotion as PromotionModel;
use App\Models\Qualification as QualificationModel;
use App\Models\SimpleDiscount as SimpleDiscountModel;
use Lattice\Promotions\DirectDiscountPromotion;
use Lattice\Promotions\Promotion as LatticePromotion;
use RuntimeException;

class DirectDiscountPromotionStrategy extends BaseLatticePromotionStrategy
{
    public function supports(PromotionModel $promotion): bool
    {
        return $promotion->promotionable instanceof DirectDiscountPromotionModel;
    }

    public function make(PromotionModel $promotion): LatticePromotion
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof DirectDiscountPromotionModel) {
            throw $this->unsupportedPromotionableType($promotion);
        }

        $discount = $promotionable->discount;

        if (! $discount instanceof SimpleDiscountModel) {
            throw new RuntimeException(
                'Direct discount promotion is missing its simple discount relation.',
            );
        }

        $qualificationIndex = $this->qualificationIndex($promotion);

        $rootQualification = $this->resolveRootQualification(
            $promotion,
            $promotionable,
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
        $directQualification = $directPromotion->relationLoaded('qualification')
            ? $directPromotion->qualification
            : null;

        if ($directQualification instanceof QualificationModel) {
            return $directQualification;
        }

        $qualification = $promotion->qualifications->first(
            fn (QualificationModel $candidate): bool => $candidate->context ===
                QualificationContext::Primary->value &&
                $candidate->qualifiable_type ===
                    $directPromotion->getMorphClass() &&
                (int) $candidate->qualifiable_id ===
                    (int) $directPromotion->getKey(),
        );

        if ($qualification instanceof QualificationModel) {
            return $qualification;
        }

        throw new RuntimeException(
            'Direct discount promotion is missing its primary qualification.',
        );
    }
}
