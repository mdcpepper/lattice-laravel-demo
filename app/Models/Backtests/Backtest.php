<?php

namespace App\Models\Backtests;

use App\Enums\BacktestStatus;
use App\Models\Concerns\HasRouteUlid;
use App\Models\Promotions\PromotionRedemption;
use App\Models\Promotions\PromotionStack;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Backtest extends Model
{
    /** @use HasFactory<\Database\Factories\BacktestFactory> */
    use HasFactory;

    use HasRouteUlid;

    protected static function booted(): void
    {
        static::deleting(function (self $backtest): void {
            PromotionRedemption::query()
                ->where(
                    'redeemable_type',
                    new BacktestedCartItem()->getMorphClass(),
                )
                ->whereIn(
                    'redeemable_id',
                    $backtest
                        ->simulatedCartItems()
                        ->select('backtested_cart_items.id'),
                )
                ->delete();
        });
    }

    protected $fillable = [
        'promotion_stack_id',
        'total_carts',
        'processed_carts',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'status' => BacktestStatus::class,
        ];
    }

    /**
     * @return BelongsTo<PromotionStack, Backtest>
     */
    public function promotionStack(): BelongsTo
    {
        return $this->belongsTo(PromotionStack::class);
    }

    /**
     * @return HasMany<BacktestedCart, Backtest>
     */
    public function backtestedCarts(): HasMany
    {
        return $this->hasMany(BacktestedCart::class);
    }

    /**
     * @return HasManyThrough<BacktestedCartItem, BacktestedCart, Backtest>
     */
    public function simulatedCartItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            BacktestedCartItem::class,
            BacktestedCart::class,
        );
    }
}
