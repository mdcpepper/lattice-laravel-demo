<?php

namespace App\Models;

use App\Casts\PercentageBasisPointsCast;
use App\Contracts\Discount as DiscountContract;
use App\Enums\MixAndMatchDiscountKind;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Model;

class MixAndMatchDiscount extends Model implements DiscountContract
{
    use HasRouteUlid;

    protected $casts = [
        'kind' => MixAndMatchDiscountKind::class,
        'percentage' => PercentageBasisPointsCast::class,
    ];

    protected $fillable = ['kind', 'percentage', 'amount', 'amount_currency'];

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
