<?php

namespace App\Models;

use App\Jobs\DispatchCartRecalculationRequest;
use App\Models\Cart\CartItem;
use App\Models\Concerns\BelongsToCurrentTeam;
use App\Models\Concerns\HasRouteUlid;
use ArrayAccess;
use Cknow\Money\Casts\MoneyIntegerCast;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Tags\HasTags;

class Product extends Model
{
    use BelongsToCurrentTeam;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasRouteUlid;
    use HasTags {
        syncTags as protected syncModelTags;
    }

    protected $fillable = ['team_id', 'name'];

    protected $casts = [
        'price' => MoneyIntegerCast::class.':GBP',
    ];

    public function getMorphClass(): string
    {
        return 'product';
    }

    public function syncTags(string|array|ArrayAccess $tags): Product
    {
        $originalTagIds = $this->tagIds();

        $this->syncModelTags($tags);

        if ($originalTagIds !== $this->tagIds()) {
            $this->queueRecalculationsForLinkedCarts();
        }

        return $this;
    }

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

    /**
     * @return array<int, int>
     */
    private function tagIds(): array
    {
        $tagIds = $this->tags()
            ->pluck('tags.id')
            ->map(fn (mixed $tagId): int => (int) $tagId)
            ->all();

        sort($tagIds);

        return $tagIds;
    }

    private function queueRecalculationsForLinkedCarts(): void
    {
        $cartIds = CartItem::query()
            ->where('product_id', $this->getKey())
            ->distinct()
            ->pluck('cart_id');

        foreach ($cartIds as $cartId) {
            DispatchCartRecalculationRequest::dispatch((int) $cartId);
        }
    }
}
