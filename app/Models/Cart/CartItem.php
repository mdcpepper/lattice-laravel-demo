<?php

namespace App\Models\Cart;

use App\Models\Concerns\HasRouteUlid;
use App\Models\Model;
use App\Models\Product;
use App\Models\Promotions\PromotionRedemption;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartItem extends Model
{
    use HasFactory;
    use HasRouteUlid;
    use SoftDeletes;

    protected $fillable = [
        'cart_id',
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

    public function getMorphClass(): string
    {
        return 'cart_item';
    }

    /**
     * @return BelongsTo<Cart, CartItem>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Product, CartItem>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return MorphMany<PromotionRedemption, CartItem>
     */
    public function redemptions(): MorphMany
    {
        return $this->morphMany(PromotionRedemption::class, 'redeemable');
    }
}
