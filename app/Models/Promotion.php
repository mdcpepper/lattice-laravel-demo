<?php

namespace App\Models;

use App\Enums\QualificationContext;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Promotion extends Model
{
    protected $casts = [
        "monetary_budget" => MoneyIntegerCast::class . ":GBP",
    ];

    protected $fillable = [
        "name",
        "application_budget",
        "monetary_budget",
        "promotionable_type",
        "promotionable_id",
    ];

    /**
     * @param Builder<Promotion> $query
     * @return Builder<Promotion>
     */
    public function scopeWithGraph(Builder $query): Builder
    {
        return $query->with([
            "promotionable" => function (MorphTo $morphTo): void {
                $morphTo->morphWith(self::promotionableGraph());
            },
            "qualifications.parent",
            "qualifications.children",
            "qualifications.rules.tags",
        ]);
    }

    /**
     * @return array<class-string<Model>, array<int, string>>
     */
    protected static function promotionableGraph(): array
    {
        return [
            DirectDiscountPromotion::class => [
                "discount",
                "qualification.rules.tags",
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
     * @return HasMany<Qualification, Promotion>
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class);
    }

    public function primaryQualification(): HasOne
    {
        return $this->hasOne(Qualification::class)
            ->whereNull("qualifiable_type")
            ->whereNull("qualifiable_id")
            ->where("context", QualificationContext::Primary->value);
    }
}
