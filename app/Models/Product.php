<?php

namespace App\Models;

use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Tags\HasTags;

class Product extends Model
{
    use HasTags;

    protected $casts = [
        'price' => MoneyIntegerCast::class.':GBP',
    ];

    protected $fillable = ['name'];

    /**
     * @return BelongsTo<Category, Product>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return array<int, string>
     */
    public function getTagsArrayAttribute(): array
    {
        return $this->tags
            ->map(fn ($tag): string => $tag->getTranslation('name', 'en'))
            ->filter(fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }
}
