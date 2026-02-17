<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PercentageBasisPointsCast;
use App\Contracts\Discount as DiscountContract;
use App\Enums\TieredThresholdDiscountKind;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TieredThresholdDiscount extends Model implements DiscountContract
{
    use HasRouteUlid;

    protected $fillable = ['kind', 'percentage', 'amount', 'amount_currency'];

    protected $casts = [
        'kind' => TieredThresholdDiscountKind::class,
        'percentage' => PercentageBasisPointsCast::class,
    ];

    /**
     * @return HasMany<TieredThresholdTier, TieredThresholdDiscount>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(
            TieredThresholdTier::class,
            'tiered_threshold_discount_id',
        );
    }

    public function discountPercentage(): ?float
    {
        return is_null($this->percentage) ? null : (float) $this->percentage;
    }

    public function discountAmount(): ?int
    {
        return is_null($this->amount) ? null : (int) $this->amount;
    }

    public function discountAmountCurrency(): ?string
    {
        return is_null($this->amount_currency)
            ? null
            : (string) $this->amount_currency;
    }
}
