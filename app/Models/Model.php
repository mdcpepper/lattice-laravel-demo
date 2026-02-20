<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class Model extends EloquentModel
{
    /**
     * @throws Exception
     */
    public static function getMorphString(): string
    {
        return new static()->getMorphClass();
    }

    /**
     * @throws Exception
     */
    public function getMorphClass(): string
    {
        throw new Exception('The model should implement `getMorphClass`');
    }
}
