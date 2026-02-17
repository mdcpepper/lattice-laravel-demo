<?php

namespace App\Models;

use App\Enums\QualificationContext;
use App\Models\Concerns\HasRouteUlid;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Promotion extends Model
{
    use HasRouteUlid;

    protected $casts = [
        'monetary_budget' => MoneyIntegerCast::class.':GBP',
    ];

    protected $fillable = [
        'team_id',
        'name',
        'application_budget',
        'monetary_budget',
        'promotionable_type',
        'promotionable_id',
    ];

    /**
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeWithGraph(Builder $query): Builder
    {
        return $query->with([
            'promotionable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith(self::promotionableGraph());
            },
            'qualifications.parent',
            'qualifications.children',
            'qualifications.rules.tags',
        ]);
    }

    /**
     * @return array<class-string<Model>, array<int, string>>
     */
    protected static function promotionableGraph(): array
    {
        return [
            DirectDiscountPromotion::class => [
                'discount',
                'qualification.rules.tags',
            ],
            PositionalDiscountPromotion::class => [
                'discount',
                'qualification.rules.tags',
                'positions',
            ],
            MixAndMatchPromotion::class => [
                'discount',
                'slots.qualification.rules.tags',
            ],
            TieredThresholdPromotion::class => [
                'tiers.discount',
                'tiers.qualification.rules.tags',
            ],
        ];
    }

    /**
     * @return MorphTo<Model, Promotion>
     */
    public function promotionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Team, Promotion>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<Qualification, Promotion>
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class);
    }

    public function primaryQualification(): HasOne
    {
        return $this->hasOne(Qualification::class)
            ->whereNull('qualifiable_type')
            ->whereNull('qualifiable_id')
            ->where('context', QualificationContext::Primary->value);
    }

    /**
     * @return BelongsToMany<PromotionLayer, Promotion>
     */
    public function layers(): BelongsToMany
    {
        return $this->belongsToMany(
            PromotionLayer::class,
            'promotion_layer_promotion',
        )
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
