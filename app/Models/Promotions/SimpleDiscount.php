<?php

namespace App\Models\Promotions;

use App\Casts\PercentageBasisPointsCast;
use App\Contracts\Discount as DiscountContract;
use App\Enums\SimpleDiscountKind;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Model;

class SimpleDiscount extends Model implements DiscountContract
{
    use HasRouteUlid;

    protected $casts = [
        'kind' => SimpleDiscountKind::class,
        'percentage' => PercentageBasisPointsCast::class,
        'amount' => 'integer',
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
