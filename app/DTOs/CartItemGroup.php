<?php

namespace App\DTOs;

use App\Models\Product;

class CartItemGroup
{
    public function __construct(
        private Product $product,
        private int $quantity,
        private int $subtotalInMinorUnits,
        private int $totalInMinorUnits,
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
}
