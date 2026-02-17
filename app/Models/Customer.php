<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property string $name
 * @property string $email
 */
class Customer extends Authenticatable
{
    protected $fillable = ['team_id', 'name', 'email'];

    /**
     * @return BelongsTo<Team, Customer>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
