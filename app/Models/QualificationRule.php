<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QualificationRuleKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Tags\HasTags;

class QualificationRule extends Model
{
    use HasTags;

    protected $fillable = [
        "qualification_id",
        "kind",
        "group_qualification_id",
        "sort_order",
    ];

    protected $casts = [
        "kind" => QualificationRuleKind::class,
        "sort_order" => "integer",
    ];

    /**
     * @return BelongsTo<Qualification,QualificationRule>
     */
    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }

    /**
     * @return BelongsTo<Qualification,QualificationRule>
     */
    public function groupQualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class, "group_qualification_id");
    }
}
