<?php

namespace App\Models;

use App\Jobs\DispatchCartRecalculationRequest;
use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionStack extends Model
{
    /** @use HasFactory<\Database\Factories\PromotionStackFactory> */
    use HasFactory;

    use HasRouteUlid;

    protected static function booted(): void
    {
        static::saved(function (self $stack): void {
            $stack->queueRecalculationsForCarts();
        });
    }

    protected $fillable = [
        'team_id',
        'name',
        'root_layer_reference',
        'active_from',
        'active_to',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_from' => 'date',
            'active_to' => 'date',
        ];
    }

    public static function activeForTeam(int $teamId): ?self
    {
        $date = now()->toDateString();

        return static::query()
            ->where('team_id', $teamId)
            ->whereDate('active_from', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query
                    ->whereNull('active_to')
                    ->orWhereDate('active_to', '>=', $date);
            })
            ->first();
    }

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

    /**
     * @return HasMany<Cart, PromotionStack>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'promotion_stack_id');
    }

    private function queueRecalculationsForCarts(): void
    {
        $cartIds = $this->carts()->pluck('id');

        foreach ($cartIds as $cartId) {
            DispatchCartRecalculationRequest::dispatch((int) $cartId);
        }
    }
}
