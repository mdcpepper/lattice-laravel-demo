<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Qualification;
use App\Services\PromotionQualification\QualificationEvaluator;
use Illuminate\Support\Collection;

class TieredThresholdFakeQualificationEvaluator extends QualificationEvaluator
{
    /** @var array<int, bool> */
    private array $results;

    /** @var int[] */
    public array $seenQualificationIds = [];

    /**
     * @param  array<int, bool>  $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * @param  string[]  $productTagNames
     * @param  Collection<int, Qualification>  $qualificationIndex
     */
    public function evaluateQualification(
        Qualification $qualification,
        array $productTagNames,
        Collection $qualificationIndex,
    ): bool {
        $qualificationId = (int) $qualification->id;
        $this->seenQualificationIds[] = $qualificationId;

        return $this->results[$qualificationId] ?? false;
    }
}
