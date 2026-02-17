<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $slug
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /**
     * @return HasMany<Product,Category>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
