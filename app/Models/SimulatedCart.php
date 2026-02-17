<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulatedCart extends Model
{
    /** @use HasFactory<\Database\Factories\SimulatedCartFactory> */
    use HasFactory;

    use HasRouteUlid;

    protected $fillable = [
        'simulation_run_id',
        'cart_id',
        'team_id',
        'email',
        'customer_id',
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
     * @return BelongsTo<SimulationRun, SimulatedCart>
     */
    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }

    /**
     * @return BelongsTo<Cart, SimulatedCart>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Team, SimulatedCart>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Customer, SimulatedCart>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SimulatedCartItem, SimulatedCart>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SimulatedCartItem::class);
    }
}
