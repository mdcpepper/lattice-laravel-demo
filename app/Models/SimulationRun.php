<?php

namespace App\Models;

use App\Enums\SimulationRunStatus;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SimulationRun extends Model
{
    /** @use HasFactory<\Database\Factories\SimulationRunFactory> */
    use HasFactory, HasRouteUlid;

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
            'status' => SimulationRunStatus::class,
        ];
    }

    /**
     * @return BelongsTo<PromotionStack, SimulationRun>
     */
    public function promotionStack(): BelongsTo
    {
        return $this->belongsTo(PromotionStack::class);
    }

    /**
     * @return HasMany<SimulatedCart, SimulationRun>
     */
    public function simulatedCarts(): HasMany
    {
        return $this->hasMany(SimulatedCart::class);
    }

    /**
     * @return HasManyThrough<SimulatedCartItem, SimulatedCart, SimulationRun>
     */
    public function simulatedCartItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            SimulatedCartItem::class,
            SimulatedCart::class,
        );
    }
}
