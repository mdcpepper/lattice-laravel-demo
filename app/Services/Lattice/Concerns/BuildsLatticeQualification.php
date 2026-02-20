<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\Qualification;
use App\Models\Promotions\QualificationRule;
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
        Qualification $qualification,
        Collection $qualificationIndex,
    ): LatticeQualification {
        $rules = $qualification->rules->sortBy('sort_order')->values()->all();

        /** @var LatticeRule[] $latticeRules */
        $latticeRules = array_map(
            fn (QualificationRule $rule): LatticeRule => $this->makeRule(
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
        QualificationRule $rule,
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
        QualificationRule $rule,
        Collection $qualificationIndex,
    ): Qualification {
        $groupQualificationId = $rule->group_qualification_id;

        if (is_null($groupQualificationId)) {
            throw new RuntimeException(
                'Group qualification rule is missing group_qualification_id.',
            );
        }

        $groupQualification = $qualificationIndex->get(
            (int) $groupQualificationId,
        );

        if ($groupQualification instanceof Qualification) {
            return $groupQualification;
        }

        if (
            $rule->relationLoaded('groupQualification') &&
            $rule->groupQualification instanceof Qualification
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
    protected function ruleTags(QualificationRule $rule): array
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
    protected function qualificationIndex(Promotion $promotion): Collection
    {
        return $promotion->qualifications
            ->keyBy('id')
            ->map(fn ($qualification): Qualification => $qualification);
    }
}
