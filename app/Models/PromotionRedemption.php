<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionRedemption extends Model
{
    use HasFactory, HasRouteUlid;

    protected $fillable = [
        'promotion_id',
        'promotion_stack_id',
        'original_price',
        'original_price_currency',
        'final_price',
        'final_price_currency',
    ];

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

    public static function createFromApplication(
        \Lattice\PromotionApplication $application,
        PromotionStack $stack,
    ): self {
        return self::query()->create([
            'promotion_id' => $application->promotion->reference->id,
            'promotion_stack_id' => $stack->id,
            'original_price' => $application->originalPrice->amount,
            'original_price_currency' => $application->originalPrice->currency,
            'final_price' => $application->finalPrice->amount,
            'final_price_currency' => $application->finalPrice->currency,
        ]);
    }
}
