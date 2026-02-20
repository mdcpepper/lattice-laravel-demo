<?php

namespace App\Models\Cart;

use App\Models\Concerns\BelongsToCurrentTeam;
use App\Models\Concerns\HasRouteUlid;
use App\Models\Customer;
use App\Models\Model;
use App\Models\Promotions\PromotionStack;
use App\Models\Team;
use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;
    use HasRouteUlid;

    protected $fillable = [
        'team_id',
        'email',
        'customer_id',
        'promotion_stack_id',
        'subtotal',
        'subtotal_currency',
        'total',
        'total_currency',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'subtotal' => MoneyIntegerCast::class.':GBP',
            'total' => MoneyIntegerCast::class.':GBP',
        ];
    }

    public function getMorphClass(): string
    {
        return 'cart';
    }

    /**
     * @return BelongsTo<Team, Cart>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Customer, Cart>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<PromotionStack, Cart>
     */
    public function promotionStack(): BelongsTo
    {
        return $this->belongsTo(PromotionStack::class);
    }

    /**
     * @return HasMany<CartItem, Cart>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
