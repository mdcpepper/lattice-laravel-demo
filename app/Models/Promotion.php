<?php

namespace App\Models;

use App\Enums\QualificationContext;
use Cknow\Money\Casts\MoneyIntegerCast;
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
