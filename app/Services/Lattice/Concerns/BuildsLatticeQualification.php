<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Models\Promotion as PromotionModel;
use App\Models\Qualification as QualificationModel;
use App\Models\QualificationRule as QualificationRuleModel;
use Illuminate\Support\Collection;
use Lattice\Qualification as LatticeQualification;
use Lattice\Qualification\BoolOp as LatticeBoolOp;
use Lattice\Qualification\Rule as LatticeRule;
use RuntimeException;

trait BuildsLatticeQualification
{
    /**
     * @param  Collection<int, QualificationModel>  $qualificationIndex
     */
    protected function makeQualification(
        QualificationModel $qualification,
        Collection $qualificationIndex,
    ): LatticeQualification {
        $rules = $qualification->rules->sortBy('sort_order')->values()->all();

        /** @var LatticeRule[] $latticeRules */
        $latticeRules = array_map(
            fn (QualificationRuleModel $rule): LatticeRule => $this->makeRule(
                $rule,
                $qualificationIndex,
            ),
            $rules,
        );

        return new LatticeQualification(
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
    ): LatticeRule {
        $kind =
            $rule->kind instanceof QualificationRuleKind
                ? $rule->kind
                : QualificationRuleKind::from((string) $rule->kind);

        return match ($kind) {
            QualificationRuleKind::HasAll => LatticeRule::hasAll(
                $this->ruleTags($rule),
            ),
            QualificationRuleKind::HasAny => LatticeRule::hasAny(
                $this->ruleTags($rule),
            ),
            QualificationRuleKind::HasNone => LatticeRule::hasNone(
                $this->ruleTags($rule),
            ),
            QualificationRuleKind::Group => LatticeRule::group(
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

    protected function mapQualificationOp(
        QualificationOp|string $op,
    ): LatticeBoolOp {
        $op = $op instanceof QualificationOp ? $op : QualificationOp::from($op);

        return match ($op) {
            QualificationOp::And => LatticeBoolOp::AndOp,
            QualificationOp::Or => LatticeBoolOp::OrOp,
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
}
