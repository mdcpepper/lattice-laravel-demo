<?php

namespace App\Models\Promotions;

use App\Enums\QualificationContext;
use App\Jobs\DispatchCartRecalculationRequest;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Concerns\BelongsToCurrentTeam;
use App\Models\Concerns\HasRouteUlid;
use App\Models\Model;
use App\Models\Team;
use Cknow\Money\Casts\MoneyIntegerCast;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Promotion extends Model
{
    use BelongsToCurrentTeam;

    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    use HasRouteUlid;

    protected static function booted(): void
    {
        static::saved(function (self $promotion): void {
            $promotion->queueRecalculationsForLinkedCarts();
        });
    }

    public function getMorphClass(): string
    {
        return 'promotion';
    }

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
        ])->afterQuery(function (Collection $promotions): void {
            $promotions->each(
                fn (self $promotion) => $promotion->hydratePromotionableQualifications(),
            );
        });
    }

    /**
     * @return array<class-string<Model>, array<int, string>>
     */
    protected static function promotionableGraph(): array
    {
        return [
            DirectDiscountPromotion::class => [
                'discount',
            ],
            PositionalDiscountPromotion::class => [
                'discount',
                'positions',
            ],
            MixAndMatchPromotion::class => [
                'discount',
                'slots',
            ],
            TieredThresholdPromotion::class => [
                'tiers.discount',
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

    /**
     * @return HasMany<PromotionRedemption, Promotion>
     *
     * @throws \Exception
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class)->where(
            'redeemable_type',
            CartItem::getMorphString(),
        );
    }

    public function hydratePromotionableQualifications(): void
    {
        if (! $this->relationLoaded('qualifications') || ! $this->relationLoaded('promotionable')) {
            return;
        }

        $promotionable = $this->promotionable;

        if (! $promotionable instanceof Model) {
            return;
        }

        $qualifications = $this->qualifications;

        $this->hydrateQualificationOn($promotionable, $qualifications);

        if ($promotionable instanceof MixAndMatchPromotion && $promotionable->relationLoaded('slots')) {
            foreach ($promotionable->slots as $slot) {
                $this->hydrateQualificationOn($slot, $qualifications);
            }
        }

        if ($promotionable instanceof TieredThresholdPromotion && $promotionable->relationLoaded('tiers')) {
            foreach ($promotionable->tiers as $tier) {
                $this->hydrateQualificationOn($tier, $qualifications);
            }
        }
    }

    /**
     * @param  Collection<int, Qualification>  $qualifications
     */
    private function hydrateQualificationOn(Model $model, Collection $qualifications): void
    {
        $morphClass = $model->getMorphClass();
        $modelKey = $model->getKey();

        $qualification = $qualifications->first(
            fn (Qualification $q): bool => $q->qualifiable_type === $morphClass
                && $q->qualifiable_id == $modelKey
                && $q->context === QualificationContext::Primary->value,
        );

        if ($qualification instanceof Qualification) {
            $model->setRelation('qualification', $qualification);
        }
    }

    private function queueRecalculationsForLinkedCarts(): void
    {
        $stackIds = $this->layers()
            ->select('promotion_layers.promotion_stack_id')
            ->distinct()
            ->pluck('promotion_layers.promotion_stack_id');

        if ($stackIds->isEmpty()) {
            return;
        }

        $cartIds = Cart::query()
            ->whereIn('promotion_stack_id', $stackIds)
            ->pluck('id');

        foreach ($cartIds as $cartId) {
            DispatchCartRecalculationRequest::dispatch((int) $cartId);
        }
    }
}
