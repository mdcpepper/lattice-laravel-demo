<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Models\Promotions\Promotion;
use RuntimeException;

trait HandlesUnsupportedPromotionableType
{
    protected function unsupportedPromotionableType(
        Promotion $promotion,
    ): RuntimeException {
        $promotionable = $promotion->relationLoaded('promotionable')
            ? $promotion->getRelation('promotionable')
            : null;

        return new RuntimeException(
            sprintf(
                'Unsupported promotionable type [%s].',
                $promotion->promotionable_type ??
                    get_debug_type($promotionable),
            ),
        );
    }
}
