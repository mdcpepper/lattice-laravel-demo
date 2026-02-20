<?php

namespace App\Models;

use App\Models\Concerns\HasRouteUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property string $name
 * @property string $email
 */
class Customer extends Authenticatable
{
    use HasFactory;
    use HasRouteUlid;

    protected $fillable = ['team_id', 'name', 'email'];

    public function getMorphClass(): string
    {
        return 'customer';
    }

    /**
     * @return BelongsTo<Team, Customer>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
