<?php

declare(strict_types=1);

namespace App\Services\PromotionQualification;

use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Models\Promotions\Qualification;
use App\Models\Promotions\QualificationRule;
use Illuminate\Support\Collection;
use RuntimeException;

class QualificationEvaluator
{
    /**
     * @param  string[]  $productTagNames
     * @param  Collection<int, Qualification>  $qualificationIndex
     */
    public function evaluateQualification(
        Qualification $qualification,
        array $productTagNames,
        Collection $qualificationIndex,
    ): bool {
        $rules = $qualification->rules->sortBy('sort_order');

        if ($rules->isEmpty()) {
            return true;
        }

        $op =
            $qualification->op instanceof QualificationOp
                ? $qualification->op
                : QualificationOp::from((string) $qualification->op);

        if ($op === QualificationOp::And) {
            return $rules->every(
                fn (QualificationRule $rule): bool => $this->evaluateRule(
                    $rule,
                    $productTagNames,
                    $qualificationIndex,
                ),
            );
        }

        return $rules->contains(
            fn (QualificationRule $rule): bool => $this->evaluateRule(
                $rule,
                $productTagNames,
                $qualificationIndex,
            ),
        );
    }

    /**
     * @param  string[]  $productTagNames
     * @param  Collection<int, Qualification>  $qualificationIndex
     */
    private function evaluateRule(
        QualificationRule $rule,
        array $productTagNames,
        Collection $qualificationIndex,
    ): bool {
        $kind =
            $rule->kind instanceof QualificationRuleKind
                ? $rule->kind
                : QualificationRuleKind::from((string) $rule->kind);

        $ruleTags = $rule->tags
            ->map(fn ($tag): string => strtolower((string) $tag->name))
            ->values()
            ->all();

        return match ($kind) {
            QualificationRuleKind::HasAll => count($ruleTags) > 0 &&
                count(array_intersect($ruleTags, $productTagNames)) ===
                    count($ruleTags),
            QualificationRuleKind::HasAny => count(
                array_intersect($ruleTags, $productTagNames),
            ) > 0,
            QualificationRuleKind::HasNone => count(
                array_intersect($ruleTags, $productTagNames),
            ) === 0,
            QualificationRuleKind::Group => $this->evaluateGroupRule(
                $rule,
                $productTagNames,
                $qualificationIndex,
            ),
        };
    }

    /**
     * @param  string[]  $productTagNames
     * @param  Collection<int, Qualification>  $qualificationIndex
     */
    private function evaluateGroupRule(
        QualificationRule $rule,
        array $productTagNames,
        Collection $qualificationIndex,
    ): bool {
        $groupQualificationId = $rule->group_qualification_id;

        if (is_null($groupQualificationId)) {
            throw new RuntimeException(
                'Group qualification rule is missing group_qualification_id.',
            );
        }

        $groupQualification = $qualificationIndex->get(
            (int) $groupQualificationId,
        );

        if (! $groupQualification instanceof Qualification) {
            throw new RuntimeException(
                sprintf(
                    'Unable to resolve grouped qualification [%d].',
                    $groupQualificationId,
                ),
            );
        }

        return $this->evaluateQualification(
            $groupQualification,
            $productTagNames,
            $qualificationIndex,
        );
    }
}
