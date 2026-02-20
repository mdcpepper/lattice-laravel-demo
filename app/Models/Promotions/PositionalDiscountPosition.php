<?php

declare(strict_types=1);

namespace App\Models\Promotions;

use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $positional_discount_promotion_id
 * @property int $position
 * @property int $sort_order
 */
class PositionalDiscountPosition extends Model
{
    use HasRouteUlid;

    protected $fillable = [
        'positional_discount_promotion_id',
        'position',
        'sort_order',
    ];

    protected $casts = [
        'position' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<PositionalDiscountPromotion, PositionalDiscountPosition>
     */
    public function positionalDiscountPromotion(): BelongsTo
    {
        return $this->belongsTo(
            PositionalDiscountPromotion::class,
            'positional_discount_promotion_id',
        );
    }
}
