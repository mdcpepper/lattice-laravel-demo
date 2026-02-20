<?php

namespace App\Models\Backtests;

use App\Models\Cart\CartItem;
use App\Models\Concerns\HasRouteUlid;
use App\Models\Product;
use App\Models\Promotions\PromotionRedemption;
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
        'price',
        'price_currency',
        'offer_price',
        'offer_price_currency',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'price' => MoneyIntegerCast::class.':GBP',
            'offer_price' => MoneyIntegerCast::class.':GBP',
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
