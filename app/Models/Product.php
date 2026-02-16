<?php

namespace App\Models;

use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $casts = [
        "price" => MoneyIntegerCast::class . ":GBP",
    ];

    protected $fillable = ["name"];

    /**
     * @return BelongsTo<Category,Product>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
