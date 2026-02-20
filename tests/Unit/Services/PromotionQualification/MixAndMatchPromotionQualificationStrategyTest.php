<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PromotionQualification;

use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\MixAndMatchPromotion;
use App\Models\Promotions\MixAndMatchSlot;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\Qualification;
use App\Services\PromotionQualification\MixAndMatchStrategy;
use App\Services\PromotionQualification\QualificationEvaluator;
use Illuminate\Support\Collection;
use RuntimeException;

test('supports mix and match promotions only', function (): void {
    $strategy = new MixAndMatchStrategy(
        new MixAndMatchFakeQualificationEvaluator([]),
    );

    $mixAndMatchPromotion = new Promotion;
    $mixAndMatchPromotion->setRelation(
        'promotionable',
        new MixAndMatchPromotion,
    );

    $directPromotion = new Promotion;
    $directPromotion->setRelation(
        'promotionable',
        new DirectDiscountPromotion,
    );

    expect($strategy->supports($mixAndMatchPromotion))->toBeTrue();
    expect($strategy->supports($directPromotion))->toBeFalse();
});

test('returns true when any slot qualification matches', function (): void {
    $evaluator = new MixAndMatchFakeQualificationEvaluator([
        301 => false,
        302 => true,
    ]);

    $strategy = new MixAndMatchStrategy($evaluator);

    $qualificationA = mixAndMatchQualification(id: 301);
    $qualificationB = mixAndMatchQualification(id: 302);

    $slotA = new MixAndMatchSlot;
    $slotA->id = 1;
    $slotA->setRelation('qualification', $qualificationA);

    $slotB = new MixAndMatchSlot;
    $slotB->id = 2;
    $slotB->setRelation('qualification', $qualificationB);

    $mixAndMatchPromotion = new MixAndMatchPromotion;
    $mixAndMatchPromotion->setRelation('slots', collect([$slotA, $slotB]));

    $promotion = new Promotion(['name' => 'Buy 2 Get 1']);
    $promotion->setRelation('promotionable', $mixAndMatchPromotion);
    $promotion->setRelation(
        'qualifications',
        collect([$qualificationA, $qualificationB]),
    );

    expect($strategy->qualifies($promotion, ['category-b']))
        ->toBeTrue()
        ->and($evaluator->seenQualificationIds)
        ->toBe([301, 302]);
});

test('returns false when no slot qualification matches', function (): void {
    $evaluator = new MixAndMatchFakeQualificationEvaluator([
        401 => false,
        402 => false,
    ]);

    $strategy = new MixAndMatchStrategy($evaluator);

    $qualificationA = mixAndMatchQualification(id: 401);
    $qualificationB = mixAndMatchQualification(id: 402);

    $slotA = new MixAndMatchSlot;
    $slotA->id = 1;
    $slotA->setRelation('qualification', $qualificationA);

    $slotB = new MixAndMatchSlot;
    $slotB->id = 2;
    $slotB->setRelation('qualification', $qualificationB);

    $mixAndMatchPromotion = new MixAndMatchPromotion;
    $mixAndMatchPromotion->setRelation('slots', collect([$slotA, $slotB]));

    $promotion = new Promotion(['name' => 'Buy 2 Get 1']);
    $promotion->setRelation('promotionable', $mixAndMatchPromotion);
    $promotion->setRelation(
        'qualifications',
        collect([$qualificationA, $qualificationB]),
    );

    expect($strategy->qualifies($promotion, ['category-c']))
        ->toBeFalse()
        ->and($evaluator->seenQualificationIds)
        ->toBe([401, 402]);
});

test(
    'resolves slot qualification from promotion qualifications when slot relation is not loaded',
    function (): void {
        $evaluator = new MixAndMatchFakeQualificationEvaluator([501 => true]);
        $strategy = new MixAndMatchStrategy($evaluator);

        $slot = new MixAndMatchSlot;
        $slot->id = 55;

        $qualification = mixAndMatchQualification(
            id: 501,
            context: 'primary',
            qualifiableType: $slot->getMorphClass(),
            qualifiableId: 55,
        );

        $mixAndMatchPromotion = new MixAndMatchPromotion;
        $mixAndMatchPromotion->setRelation('slots', collect([$slot]));

        $promotion = new Promotion(['name' => 'Buy 2 Get 1']);
        $promotion->setRelation('promotionable', $mixAndMatchPromotion);
        $promotion->setRelation('qualifications', collect([$qualification]));

        expect($strategy->qualifies($promotion, ['category-a']))
            ->toBeTrue()
            ->and($evaluator->seenQualificationIds)
            ->toBe([501]);
    },
);

test('throws when slot qualification cannot be resolved', function (): void {
    $strategy = new MixAndMatchStrategy(
        new MixAndMatchFakeQualificationEvaluator([]),
    );

    $slot = new MixAndMatchSlot;
    $slot->id = 13;

    $mixAndMatchPromotion = new MixAndMatchPromotion;
    $mixAndMatchPromotion->setRelation('slots', collect([$slot]));

    $promotion = new Promotion(['name' => 'Buy 2 Get 1']);
    $promotion->setRelation('promotionable', $mixAndMatchPromotion);
    $promotion->setRelation('qualifications', collect());

    expect(
        fn (): bool => $strategy->qualifies($promotion, ['category-a']),
    )->toThrow(
        RuntimeException::class,
        'Mix and match slot [13] is missing its primary qualification.',
    );
});

function mixAndMatchQualification(
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

class MixAndMatchFakeQualificationEvaluator extends QualificationEvaluator
{
    /** @var array<int, bool> */
    private array $results;

    /** @var int[] */
    public array $seenQualificationIds = [];

    /**
     * @param  array<int, bool>  $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * @param  string[]  $productTagNames
     * @param  Collection<int, Qualification>  $qualificationIndex
     */
    public function evaluateQualification(
        Qualification $qualification,
        array $productTagNames,
        Collection $qualificationIndex,
    ): bool {
        $qualificationId = (int) $qualification->id;
        $this->seenQualificationIds[] = $qualificationId;

        return $this->results[$qualificationId] ?? false;
    }
}
