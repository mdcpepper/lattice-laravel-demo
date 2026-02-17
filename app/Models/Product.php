<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Tags\HasTags;

class Product extends Model
{
    use HasFactory, HasRouteUlid, HasTags;

    protected $casts = [
        'price' => MoneyIntegerCast::class.':GBP',
    ];

    protected $fillable = ['team_id', 'name'];

    /**
     * @return BelongsTo<Team, Product>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

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
