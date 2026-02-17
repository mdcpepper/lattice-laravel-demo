<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property string $name
 * @property string $email
 */
class Customer extends Authenticatable
{
    protected $fillable = ['name', 'email'];
}
