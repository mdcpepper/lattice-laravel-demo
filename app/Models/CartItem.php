<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartItem extends Model
{
    use HasFactory;
    use HasRouteUlid;
    use SoftDeletes;

    protected $fillable = [
        'cart_id',
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
}
