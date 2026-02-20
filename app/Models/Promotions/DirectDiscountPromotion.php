<?php

namespace App\Models\Promotions;

use App\Enums\QualificationContext;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $simple_discount_id
 */
class DirectDiscountPromotion extends Model
{
    use HasRouteUlid;

    protected $fillable = ['simple_discount_id'];

    /**
     * @return BelongsTo<SimpleDiscount, DirectDiscountPromotion>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(SimpleDiscount::class, 'simple_discount_id');
    }

    /**
     * @return MorphOne<Promotion, DirectDiscountPromotion>
     */
    public function promotion(): MorphOne
    {
        return $this->morphOne(Promotion::class, 'promotionable');
    }

    public function qualification(): MorphOne
    {
        return $this->morphOne(Qualification::class, 'qualifiable')->where(
            'context',
            QualificationContext::Primary->value,
        );
    }
}
