<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $name
 * @property string $slug
 * @property int|null $main_product_id
 */
class Category extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;
    use HasRouteUlid;

    protected $fillable = ['team_id', 'name', 'slug', 'main_product_id'];

    public function getMorphClass(): string
    {
        return 'category';
    }

    /**
     * @return BelongsTo<Team, Category>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<Product,Category>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return BelongsTo<Product, Category>
     */
    public function mainProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'main_product_id');
    }

    /**
     * @return HasOne<Product, Category>
     */
    public function highestPricedProduct(): HasOne
    {
        return $this->hasOne(Product::class)->ofMany('price', 'max');
    }
}
