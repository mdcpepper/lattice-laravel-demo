<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BacktestedCartItem extends Model
{
    /** @use HasFactory<\Database\Factories\BacktestedCartItemFactory> */
    use HasFactory;

    use HasRouteUlid;
    use SoftDeletes;

    protected $fillable = [
        'backtest_id',
        'backtested_cart_id',
        'cart_item_id',
        'product_id',
        'subtotal',
        'subtotal_currency',
        'total',
        'total_currency',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'subtotal' => MoneyIntegerCast::class.':GBP',
            'total' => MoneyIntegerCast::class.':GBP',
        ];
    }

    /**
     * @return BelongsTo<Backtest, BacktestedCartItem>
     */
    public function backtestRun(): BelongsTo
    {
        return $this->belongsTo(Backtest::class);
    }

    /**
     * @return BelongsTo<BacktestedCart, BacktestedCartItem>
     */
    public function backtestedCart(): BelongsTo
    {
        return $this->belongsTo(BacktestedCart::class);
    }

    /**
     * @return BelongsTo<CartItem, BacktestedCartItem>
     */
    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    /**
     * @return BelongsTo<Product, BacktestedCartItem>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return MorphMany<PromotionRedemption, BacktestedCartItem>
     */
    public function redemptions(): MorphMany
    {
        return $this->morphMany(PromotionRedemption::class, 'redeemable');
    }
}
