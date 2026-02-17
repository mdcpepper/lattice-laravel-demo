<?php

namespace App\Models;

use App\Enums\BacktestStatus;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Backtest extends Model
{
    /** @use HasFactory<\Database\Factories\BacktestFactory> */
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
