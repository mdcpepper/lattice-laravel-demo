<?php

namespace App\Models\Promotions;

use App\Enums\QualificationContext;
use App\Models\Concerns\HasRouteUlid;
use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TieredThresholdTier extends Model
{
    use HasRouteUlid;

    protected $fillable = [
        'tiered_threshold_promotion_id',
        'tiered_threshold_discount_id',
        'sort_order',
        'lower_monetary_threshold_minor',
        'lower_monetary_threshold_currency',
        'lower_item_count_threshold',
        'upper_monetary_threshold_minor',
        'upper_monetary_threshold_currency',
        'upper_item_count_threshold',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'lower_monetary_threshold_minor' => 'integer',
        'lower_item_count_threshold' => 'integer',
        'upper_monetary_threshold_minor' => 'integer',
        'upper_item_count_threshold' => 'integer',
    ];

    public function getMorphClass(): string
    {
        return 'tiered_threshold_tier';
    }

    /**
     * @return BelongsTo<TieredThresholdPromotion, TieredThresholdTier>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(
            TieredThresholdPromotion::class,
            'tiered_threshold_promotion_id',
        );
    }

    /**
     * @return BelongsTo<TieredThresholdDiscount, TieredThresholdTier>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(
            TieredThresholdDiscount::class,
            'tiered_threshold_discount_id',
        );
    }

    /**
     * @return MorphOne<Qualification, TieredThresholdTier>
     */
    public function qualification(): MorphOne
    {
        return $this->morphOne(Qualification::class, 'qualifiable')->where(
            'context',
            QualificationContext::Primary->value,
        );
    }
}
