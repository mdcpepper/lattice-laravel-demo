<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PromotionQualification;

use App\Models\MixAndMatchPromotion;
use App\Models\Promotion;
use App\Models\Qualification;
use App\Models\TieredThresholdPromotion;
use App\Models\TieredThresholdTier;
use App\Services\PromotionQualification\TieredThresholdStrategy;
use RuntimeException;
use Tests\Helpers\TieredThresholdFakeQualificationEvaluator;

test('supports tiered threshold promotions only', function (): void {
    $strategy = new TieredThresholdStrategy(
        new TieredThresholdFakeQualificationEvaluator([]),
    );

    $tieredThresholdPromotion = new Promotion;
    $tieredThresholdPromotion->setRelation(
        'promotionable',
        new TieredThresholdPromotion,
    );

    $mixAndMatchPromotion = new Promotion;
    $mixAndMatchPromotion->setRelation(
        'promotionable',
        new MixAndMatchPromotion,
    );

    expect($strategy->supports($tieredThresholdPromotion))->toBeTrue();
    expect($strategy->supports($mixAndMatchPromotion))->toBeFalse();
});

test('returns true when any tier qualification matches', function (): void {
    $evaluator = new TieredThresholdFakeQualificationEvaluator([
        601 => false,
        602 => true,
    ]);

    $strategy = new TieredThresholdStrategy($evaluator);

    $qualificationA = tieredQualification(id: 601);
    $qualificationB = tieredQualification(id: 602);

    $tierA = new TieredThresholdTier;
    $tierA->id = 1;
    $tierA->setRelation('qualification', $qualificationA);

    $tierB = new TieredThresholdTier;
    $tierB->id = 2;
    $tierB->setRelation('qualification', $qualificationB);

    $tieredThresholdPromotion = new TieredThresholdPromotion;
    $tieredThresholdPromotion->setRelation('tiers', collect([$tierA, $tierB]));

    $promotion = new Promotion(['name' => 'Tiered']);
    $promotion->setRelation('promotionable', $tieredThresholdPromotion);
    $promotion->setRelation(
        'qualifications',
        collect([$qualificationA, $qualificationB]),
    );

    expect($strategy->qualifies($promotion, ['eligible']))
        ->toBeTrue()
        ->and($evaluator->seenQualificationIds)
        ->toBe([601, 602]);
});

test(
    'resolves tier qualification from promotion qualifications when tier relation is not loaded',
    function (): void {
        $evaluator = new TieredThresholdFakeQualificationEvaluator([
            701 => true,
        ]);
        $strategy = new TieredThresholdStrategy($evaluator);

        $tier = new TieredThresholdTier;
        $tier->id = 77;

        $qualification = tieredQualification(
            id: 701,
            context: 'primary',
            qualifiableType: $tier->getMorphClass(),
            qualifiableId: 77,
        );

        $tieredThresholdPromotion = new TieredThresholdPromotion;
        $tieredThresholdPromotion->setRelation('tiers', collect([$tier]));

        $promotion = new Promotion(['name' => 'Tiered']);
        $promotion->setRelation('promotionable', $tieredThresholdPromotion);
        $promotion->setRelation('qualifications', collect([$qualification]));

        expect($strategy->qualifies($promotion, ['eligible']))
            ->toBeTrue()
            ->and($evaluator->seenQualificationIds)
            ->toBe([701]);
    },
);

test('throws when tier qualification cannot be resolved', function (): void {
    $strategy = new TieredThresholdStrategy(
        new TieredThresholdFakeQualificationEvaluator([]),
    );

    $tier = new TieredThresholdTier;
    $tier->id = 13;

    $tieredThresholdPromotion = new TieredThresholdPromotion;
    $tieredThresholdPromotion->setRelation('tiers', collect([$tier]));

    $promotion = new Promotion(['name' => 'Tiered']);
    $promotion->setRelation('promotionable', $tieredThresholdPromotion);
    $promotion->setRelation('qualifications', collect());

    expect(
        fn (): bool => $strategy->qualifies($promotion, ['eligible']),
    )->toThrow(
        RuntimeException::class,
        'Tiered threshold tier [13] is missing its primary qualification.',
    );
});

function tieredQualification(
    int $id,
    string $context = 'primary',
    ?string $qualifiableType = null,
    ?int $qualifiableId = null,
): Qualification {
    $qualification = new Qualification;
    $qualification->id = $id;
    $qualification->context = $context;
    $qualification->qualifiable_type = $qualifiableType;
    $qualification->qualifiable_id = $qualifiableId;
    $qualification->setRelation('rules', collect());

    return $qualification;
}
