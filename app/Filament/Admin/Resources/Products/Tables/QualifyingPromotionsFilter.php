<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Models\Product;
use App\Models\Promotion;
use App\Services\ProductQualificationChecker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QualifyingPromotionsFilter
{
    private const NONE_FILTER_VALUE = '__none__';

    public static function make(
        ProductQualificationChecker $checker,
        ?int $teamId,
    ): SelectFilter {
        return SelectFilter::make('qualifying_promotions')
            ->label('Qualifying Promotions')
            ->searchable()
            ->options(fn (): array => self::options($teamId))
            ->multiple()
            ->attribute(fn (): string => 'id')
            ->query(
                fn (Builder $query, array $data): Builder => self::applyQuery(
                    $query,
                    $data,
                    $checker,
                    $teamId,
                ),
            );
    }

    private static function options(?int $teamId): array
    {
        $promotionQuery = Promotion::query();

        if ($teamId !== null) {
            $promotionQuery->where('team_id', $teamId);
        }

        $promotionOptions = $promotionQuery
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return [self::NONE_FILTER_VALUE => 'None'] + $promotionOptions;
    }

    private static function applyQuery(
        Builder $query,
        array $data,
        ProductQualificationChecker $checker,
        ?int $teamId,
    ): Builder {
        $selectedValues = collect($data['values'] ?? []);
        $includeNone = $selectedValues->contains(self::NONE_FILTER_VALUE);
        $promotionIds = self::selectedPromotionIds($selectedValues);

        if ($promotionIds === [] && ! $includeNone) {
            return $query;
        }

        $selectedPromotions = self::selectedPromotions($promotionIds, $teamId);
        $candidateProductIds = (clone $query)
            ->pluck('products.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($candidateProductIds === []) {
            return $query->whereKey([]);
        }

        $qualifyingProductIds = Product::query()
            ->whereKey($candidateProductIds)
            ->with('tags')
            ->get()
            ->filter(
                fn (Product $product): bool => self::productMatches(
                    $product,
                    $checker,
                    $teamId,
                    $selectedPromotions,
                    $includeNone,
                ),
            )
            ->pluck('id')
            ->all();

        return $query->whereKey($qualifyingProductIds);
    }

    /**
     * @param  Collection<int, mixed>  $selectedValues
     * @return int[]
     */
    private static function selectedPromotionIds(Collection $selectedValues): array
    {
        return $selectedValues
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();
    }

    /**
     * @param  int[]  $promotionIds
     * @return Collection<int, Promotion>
     */
    private static function selectedPromotions(
        array $promotionIds,
        ?int $teamId,
    ): Collection {
        if ($promotionIds === []) {
            return collect();
        }

        $promotionQuery = Promotion::query()->whereIn('id', $promotionIds);

        if ($teamId !== null) {
            $promotionQuery->where('team_id', $teamId);
        }

        return $promotionQuery->withGraph()->get();
    }

    /**
     * @param  Collection<int, Promotion>  $selectedPromotions
     */
    private static function productMatches(
        Product $product,
        ProductQualificationChecker $checker,
        ?int $teamId,
        Collection $selectedPromotions,
        bool $includeNone,
    ): bool {
        $matchesSelectedPromotions = $selectedPromotions->isNotEmpty() &&
            $checker->qualifiesForAnyPromotion($product, $selectedPromotions);

        if (! $includeNone) {
            return $matchesSelectedPromotions;
        }

        $matchesNoPromotions = ! $checker->hasAnyQualifyingPromotion(
            $product,
            $teamId,
        );

        return $matchesSelectedPromotions || $matchesNoPromotions;
    }
}
