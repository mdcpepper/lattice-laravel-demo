<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Models\Qualification;
use App\Models\QualificationRule;

function qualification(
    int $id,
    string $context = QualificationContext::Primary->value,
    ?string $qualifiableType = null,
    ?int $qualifiableId = null,
    QualificationOp|string $op = QualificationOp::And,
): Qualification {
    $qualification = new Qualification;

    $qualification->id = $id;
    $qualification->context = $context;
    $qualification->qualifiable_type = $qualifiableType;
    $qualification->qualifiable_id = $qualifiableId;
    $qualification->op = $op;

    $qualification->setRelation('rules', collect());

    return $qualification;
}

/**
 * @param  string[]  $tags
 */
function qualificationRule(
    int $id,
    QualificationRuleKind|string $kind,
    int $sortOrder,
    ?int $groupQualificationId = null,
    array $tags = [],
): QualificationRule {
    $rule = new QualificationRule;

    $rule->id = $id;
    $rule->kind = $kind;
    $rule->sort_order = $sortOrder;
    $rule->group_qualification_id = $groupQualificationId;

    $rule->setRelation(
        'tags',
        collect(
            array_map(
                fn (string $tag): object => (object) ['name' => $tag],
                $tags,
            ),
        ),
    );

    return $rule;
}
