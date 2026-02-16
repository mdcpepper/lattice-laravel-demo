<?php

namespace App\Models;

use App\Casts\PercentageBasisPointsCast;
use App\Enums\SimpleDiscountKind;
use Illuminate\Database\Eloquent\Model;

class SimpleDiscount extends Model
{
    protected $casts = [
        "kind" => SimpleDiscountKind::class,
        "percentage" => PercentageBasisPointsCast::class,
    ];

    protected $fillable = ["kind", "percentage", "amount", "amount_currency"];
}
