<?php

namespace App\Models\Promotions;

use App\Enums\QualificationContext;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class MixAndMatchSlot extends Model
{
    use HasRouteUlid;

    protected $fillable = [
        'mix_and_match_promotion_id',
        'min',
        'max',
        'sort_order',
    ];

    protected $casts = [
        'min' => 'integer',
        'max' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<MixAndMatchPromotion, MixAndMatchSlot>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(
            MixAndMatchPromotion::class,
            'mix_and_match_promotion_id',
        );
    }

    /**
     * @return MorphOne<Qualification, MixAndMatchSlot>
     */
    public function qualification(): MorphOne
    {
        return $this->morphOne(Qualification::class, 'qualifiable')->where(
            'context',
            QualificationContext::Primary->value,
        );
    }
}
