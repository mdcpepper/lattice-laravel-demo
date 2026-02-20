<?php

declare(strict_types=1);

namespace App\Models\Promotions;

use App\Enums\QualificationOp;
use App\Models\Concerns\HasRouteUlid;
use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Qualification extends Model
{
    use HasRouteUlid;

    protected $fillable = [
        'promotion_id',
        'qualifiable_type',
        'qualifiable_id',
        'parent_qualification_id',
        'context',
        'op',
        'sort_order',
    ];

    protected $casts = [
        'op' => QualificationOp::class,
        'sort_order' => 'integer',
    ];

    public function getMorphClass(): string
    {
        return 'qualification';
    }

    /**
     * @return BelongsTo<Promotion, Qualification>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * @return MorphTo<Model, Qualification>
     */
    public function qualifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Qualification, Qualification>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_qualification_id');
    }

    /**
     * @return HasMany<Qualification, Qualification>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_qualification_id');
    }

    /**
     * @return HasMany<QualificationRule,Qualification>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(QualificationRule::class);
    }

    /**
     * @return HasMany<QualificationRule,Qualification>
     */
    public function groupedByRules(): HasMany
    {
        return $this->hasMany(
            QualificationRule::class,
            'group_qualification_id',
        );
    }
}
