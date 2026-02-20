<?php

namespace App\Models\Promotions;

use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TieredThresholdPromotion extends Model
{
    use HasRouteUlid;

    protected $fillable = [];

    /**
     * @return HasMany<TieredThresholdTier, TieredThresholdPromotion>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(
            TieredThresholdTier::class,
            'tiered_threshold_promotion_id',
        );
    }

    /**
     * @return MorphOne<Promotion, TieredThresholdPromotion>
     */
    public function promotion(): MorphOne
    {
        return $this->morphOne(Promotion::class, 'promotionable');
    }
}
