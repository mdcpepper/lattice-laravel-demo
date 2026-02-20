<?php

namespace App\Models\Promotions;

use App\Models\Concerns\HasRouteUlid;
use App\Models\Model;
use Cknow\Money\Casts\MoneyIntegerCast;
use Database\Factories\PromotionRedemptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lattice\PromotionRedemption as LatticePromotionRedemption;

class PromotionRedemption extends Model
{
    /** @use HasFactory<PromotionRedemptionFactory> */
    use HasFactory;

    use HasRouteUlid;

    protected $fillable = [
        'promotion_id',
        'promotion_stack_id',
        'redeemable_type',
        'redeemable_id',
        'sort_order',
        'redemption_idx',
        'original_price',
        'original_price_currency',
        'final_price',
        'final_price_currency',
    ];

    public static function createFromRedemption(
        LatticePromotionRedemption $redemption,
        PromotionStack $stack,
        Model $redeemable,
        int $sortOrder = 0,
    ): self {
        return self::query()->create([
            'promotion_id' => $redemption->promotion->reference->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => $redeemable->getMorphClass(),
            'redeemable_id' => $redeemable->getKey(),
            'sort_order' => $sortOrder,
            'redemption_idx' => $redemption->redemptionIdx,
            'original_price' => $redemption->originalPrice->amount,
            'original_price_currency' => $redemption->originalPrice->currency,
            'final_price' => $redemption->finalPrice->amount,
            'final_price_currency' => $redemption->finalPrice->currency,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'original_price' => MoneyIntegerCast::class.':GBP',
            'final_price' => MoneyIntegerCast::class.':GBP',
        ];
    }

    public function getMorphClass(): string
    {
        return 'promotion_redemption';
    }

    /**
     * @return MorphTo<Model, PromotionRedemption>
     */
    public function redeemable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Promotion, PromotionRedemption>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * @return BelongsTo<PromotionStack, PromotionRedemption>
     */
    public function promotionStack(): BelongsTo
    {
        return $this->belongsTo(PromotionStack::class);
    }
}
