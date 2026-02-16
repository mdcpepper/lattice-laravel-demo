<?php

namespace App\Models;

use App\Enums\QualificationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class DirectDiscountPromotion extends Model
{
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
