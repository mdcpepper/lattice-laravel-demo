<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $slug
 */
class Category extends Model
{
    use HasFactory;
    use HasRouteUlid;

    protected $fillable = ['team_id', 'name', 'slug'];

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
}
