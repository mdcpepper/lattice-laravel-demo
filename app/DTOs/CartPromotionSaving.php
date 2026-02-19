<?php

namespace App\DTOs;

class CartPromotionSaving
{
    public function __construct(
        private string $promotionName,
        private int $redemptionCount,
        private int $itemCount,
        private int $savingsInMinorUnits,
    ) {}

    public function promotionName(): string
    {
        return $this->promotionName;
    }

    public function redemptionCount(): int
    {
        return $this->redemptionCount;
    }

    public function itemCount(): int
    {
        return $this->itemCount;
    }

    public function savingsInMinorUnits(): int
    {
        return $this->savingsInMinorUnits;
    }
}
