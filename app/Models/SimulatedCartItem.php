<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimulatedCartItem extends Model
{
    /** @use HasFactory<\Database\Factories\SimulatedCartItemFactory> */
    use HasFactory;

    use HasRouteUlid;
    use SoftDeletes;

    protected $fillable = [
        'simulation_run_id',
        'simulated_cart_id',
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
     * @return BelongsTo<SimulationRun, SimulatedCartItem>
     */
    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }

    /**
     * @return BelongsTo<SimulatedCart, SimulatedCartItem>
     */
    public function simulatedCart(): BelongsTo
    {
        return $this->belongsTo(SimulatedCart::class);
    }

    /**
     * @return BelongsTo<CartItem, SimulatedCartItem>
     */
    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    /**
     * @return BelongsTo<Product, SimulatedCartItem>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return MorphMany<PromotionRedemption, SimulatedCartItem>
     */
    public function redemptions(): MorphMany
    {
        return $this->morphMany(PromotionRedemption::class, 'redeemable');
    }
}
