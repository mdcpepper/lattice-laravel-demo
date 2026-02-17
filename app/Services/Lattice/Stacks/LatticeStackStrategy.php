<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Models\PromotionStack as PromotionStackModel;
use Lattice\Stack as LatticeStack;

interface LatticeStackStrategy
{
    public function supports(PromotionStackModel $stack): bool;

    public function make(PromotionStackModel $stack): LatticeStack;
}
