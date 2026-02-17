<?php

namespace App\Models;

use App\Enums\QualificationContext;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $mix_and_match_discount_id
 */
class MixAndMatchPromotion extends Model
{
    use HasRouteUlid;

    protected $fillable = ['mix_and_match_discount_id'];

    /**
     * @return BelongsTo<MixAndMatchDiscount, MixAndMatchPromotion>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(
            MixAndMatchDiscount::class,
            'mix_and_match_discount_id',
        );
    }

    /**
     * @return MorphOne<Promotion, MixAndMatchPromotion>
     */
    public function promotion(): MorphOne
    {
        return $this->morphOne(Promotion::class, 'promotionable');
    }

    /**
     * @return HasMany<MixAndMatchSlot, MixAndMatchPromotion>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(
            MixAndMatchSlot::class,
            'mix_and_match_promotion_id',
        );
    }

    public function qualification(): MorphOne
    {
        return $this->morphOne(Qualification::class, 'qualifiable')->where(
            'context',
            QualificationContext::Primary->value,
        );
    }
}
