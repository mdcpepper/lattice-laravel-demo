<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionStack extends Model
{
    /** @use HasFactory<\Database\Factories\PromotionStackFactory> */
    use HasFactory, HasRouteUlid;

    protected $fillable = ['team_id', 'name', 'root_layer_reference'];

    /**
     * @return BelongsTo<Team, PromotionStack>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<PromotionLayer, PromotionStack>
     */
    public function layers(): HasMany
    {
        return $this->hasMany(PromotionLayer::class)->orderBy('sort_order');
    }
}
