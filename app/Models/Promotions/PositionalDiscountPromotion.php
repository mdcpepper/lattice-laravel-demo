<?php

declare(strict_types=1);

namespace App\Models\Promotions;

use App\Models\Concerns\HasRouteUlid;
use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $size
 * @property int $simple_discount_id
 */
class PositionalDiscountPromotion extends Model
{
    use HasRouteUlid;

    protected $fillable = ['size', 'simple_discount_id'];

    protected $casts = [
        'size' => 'integer',
    ];

    public function getMorphClass(): string
    {
        return 'positional_discount_promotion';
    }

    /**
     * @return BelongsTo<SimpleDiscount, PositionalDiscountPromotion>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(SimpleDiscount::class, 'simple_discount_id');
    }

    /**
     * @return HasMany<PositionalDiscountPosition, PositionalDiscountPromotion>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(
            PositionalDiscountPosition::class,
            'positional_discount_promotion_id',
        );
    }

    /**
     * @return MorphOne<Promotion, PositionalDiscountPromotion>
     */
    public function promotion(): MorphOne
    {
        return $this->morphOne(Promotion::class, 'promotionable');
    }

    public function qualification(): MorphOne
    {
        return $this->morphOne(Qualification::class, 'qualifiable')->where(
            'context',
            'primary',
        );
    }
}
