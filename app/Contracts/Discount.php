<?php

namespace App\Contracts;

interface Discount
{
    public function discountPercentage(): ?float;

    public function discountAmount(): ?int;

    public function discountAmountCurrency(): ?string;
}
