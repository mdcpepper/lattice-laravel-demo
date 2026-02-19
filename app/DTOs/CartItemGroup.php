<?php

namespace App\DTOs;

use App\Models\Product;

class CartItemGroup
{
    /**
     * @param  array<int, string>  $promotionNames
     */
    public function __construct(
        private Product $product,
        private int $quantity,
        private int $subtotalInMinorUnits,
        private int $totalInMinorUnits,
        private array $promotionNames = [],
    ) {}

    public function product(): Product
    {
        return $this->product;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function subtotalInMinorUnits(): int
    {
        return $this->subtotalInMinorUnits;
    }

    public function totalInMinorUnits(): int
    {
        return $this->totalInMinorUnits;
    }

    /**
     * @return array<int, string>
     */
    public function promotionNames(): array
    {
        return $this->promotionNames;
    }
}
