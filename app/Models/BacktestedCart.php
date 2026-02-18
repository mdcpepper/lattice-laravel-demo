<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BacktestedCart extends Model
{
    /** @use HasFactory<\Database\Factories\BacktestedCartFactory> */
    use HasFactory;

    use HasRouteUlid;

    protected $fillable = [
        'backtest_id',
        'cart_id',
        'team_id',
        'email',
        'customer_id',
        'subtotal',
        'subtotal_currency',
        'total',
        'total_currency',
        'processing_time',
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
     * @return BelongsTo<Backtest, BacktestedCart>
     */
    public function backtest(): BelongsTo
    {
        return $this->belongsTo(Backtest::class);
    }

    /**
     * @return BelongsTo<Cart, BacktestedCart>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Team, BacktestedCart>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Customer, BacktestedCart>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<BacktestedCartItem, BacktestedCart>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BacktestedCartItem::class);
    }
}
