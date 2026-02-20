<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Promotions\Promotion;
use App\Services\PromotionQualification\PromotionQualificationStrategy;
use Closure;

class FakePromotionQualificationStrategy implements PromotionQualificationStrategy
{
    /** @var list<string> */
    public array $qualifiedPromotionNames = [];

    /**
     * @param  Closure(Promotion): bool  $supports
     * @param  Closure(Promotion, array<int, string>): bool  $qualifies
     */
    public function __construct(
        private readonly Closure $supports,
        private readonly Closure $qualifies,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return ($this->supports)($promotion);
    }

    /**
     * @param  string[]  $productTagNames
     */
    public function qualifies(
        Promotion $promotion,
        array $productTagNames,
    ): bool {
        $this->qualifiedPromotionNames[] = (string) $promotion->name;

        return ($this->qualifies)($promotion, $productTagNames);
    }
}
