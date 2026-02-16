<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PromotionQualification;

use App\Models\DirectDiscountPromotion;
use App\Models\MixAndMatchPromotion;
use App\Models\Promotion;
use App\Models\Qualification;
use App\Services\PromotionQualification\DirectDiscountStrategy;
use App\Services\PromotionQualification\QualificationEvaluator;
use Illuminate\Support\Collection;
use RuntimeException;

test('supports direct discount promotions only', function (): void {
    $strategy = new DirectDiscountStrategy(new FakeQualificationEvaluator([]));

    $directPromotion = new Promotion;
    $directPromotion->setRelation(
        'promotionable',
        new DirectDiscountPromotion,
    );

    $mixAndMatchPromotion = new Promotion;
    $mixAndMatchPromotion->setRelation(
        'promotionable',
        new MixAndMatchPromotion,
    );

    expect($strategy->supports($directPromotion))->toBeTrue();
    expect($strategy->supports($mixAndMatchPromotion))->toBeFalse();
});

test(
    'qualifies using a loaded direct qualification relation',
    function (): void {
        $evaluator = new FakeQualificationEvaluator([100 => true]);
        $strategy = new DirectDiscountStrategy($evaluator);

        $qualification = qualification(id: 100);

        $directPromotion = new DirectDiscountPromotion;
        $directPromotion->id = 10;
        $directPromotion->setRelation('qualification', $qualification);

        $promotion = new Promotion(['name' => '10% Off']);
        $promotion->setRelation('promotionable', $directPromotion);
        $promotion->setRelation('qualifications', collect([$qualification]));

        expect($strategy->qualifies($promotion, ['eligible']))
            ->toBeTrue()
            ->and($evaluator->seenQualificationIds)
            ->toBe([100]);
    },
);

test(
    'resolves direct qualification from promotion qualifications when relation is not loaded',
    function (): void {
        $evaluator = new FakeQualificationEvaluator([201 => true]);
        $strategy = new DirectDiscountStrategy($evaluator);

        $directPromotion = new DirectDiscountPromotion;
        $directPromotion->id = 42;

        $qualification = qualification(
            id: 201,
            context: 'primary',
            qualifiableType: $directPromotion->getMorphClass(),
            qualifiableId: 42,
        );

        $promotion = new Promotion(['name' => '10% Off']);
        $promotion->setRelation('promotionable', $directPromotion);
        $promotion->setRelation('qualifications', collect([$qualification]));

        expect($strategy->qualifies($promotion, ['eligible']))
            ->toBeTrue()
            ->and($evaluator->seenQualificationIds)
            ->toBe([201]);
    },
);

test('throws when direct qualification cannot be resolved', function (): void {
    $strategy = new DirectDiscountStrategy(new FakeQualificationEvaluator([]));

    $directPromotion = new DirectDiscountPromotion;
    $directPromotion->id = 77;

    $promotion = new Promotion(['name' => '10% Off']);
    $promotion->setRelation('promotionable', $directPromotion);
    $promotion->setRelation('qualifications', collect());

    expect(
        fn (): bool => $strategy->qualifies($promotion, ['eligible']),
    )->toThrow(
        RuntimeException::class,
        'Direct discount promotion is missing its primary qualification.',
    );
});

function qualification(
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

class FakeQualificationEvaluator extends QualificationEvaluator
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
