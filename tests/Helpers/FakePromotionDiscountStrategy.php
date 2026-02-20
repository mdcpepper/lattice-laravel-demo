<?php

namespace Tests\Helpers;

use App\Models\Promotions\Promotion;
use App\Services\PromotionDiscount\PromotionDiscountStrategy;
use Closure;

class FakePromotionDiscountStrategy implements PromotionDiscountStrategy
{
    /** @var list<string> */
    public array $formattedPromotionNames = [];

    public function __construct(
        private readonly Closure $supports,
        private readonly Closure $format,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return ($this->supports)($promotion);
    }

    public function format(Promotion $promotion): ?string
    {
        $this->formattedPromotionNames[] = (string) $promotion->name;

        return ($this->format)($promotion);
    }
}
