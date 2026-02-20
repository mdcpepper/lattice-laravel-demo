<?php

namespace App\Models\Promotions;

use App\Enums\PromotionLayerOutputMode;
use App\Enums\PromotionLayerOutputTargetMode;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromotionLayer extends Model
{
    /** @use HasFactory<\Database\Factories\PromotionLayerFactory> */
    use HasFactory;

    use HasRouteUlid;

    protected $fillable = [
        'promotion_stack_id',
        'reference',
        'name',
        'sort_order',
        'output_mode',
        'participating_output_mode',
        'participating_output_layer_id',
        'non_participating_output_mode',
        'non_participating_output_layer_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'output_mode' => PromotionLayerOutputMode::class,
        'participating_output_mode' => PromotionLayerOutputTargetMode::class,
        'non_participating_output_mode' => PromotionLayerOutputTargetMode::class,
    ];

    /**
     * @return BelongsTo<PromotionStack, PromotionLayer>
     */
    public function stack(): BelongsTo
    {
        return $this->belongsTo(PromotionStack::class, 'promotion_stack_id');
    }

    /**
     * @return BelongsTo<PromotionLayer, PromotionLayer>
     */
    public function participatingOutputLayer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'participating_output_layer_id');
    }

    /**
     * @return BelongsTo<PromotionLayer, PromotionLayer>
     */
    public function nonParticipatingOutputLayer(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'non_participating_output_layer_id',
        );
    }

    /**
     * @return BelongsToMany<Promotion, PromotionLayer>
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(
            Promotion::class,
            'promotion_layer_promotion',
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
