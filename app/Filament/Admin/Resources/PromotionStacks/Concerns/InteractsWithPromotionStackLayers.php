<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Concerns;

use App\Enums\PromotionLayerOutputMode;
use App\Enums\PromotionLayerOutputTargetMode;
use App\Models\PromotionLayer;
use App\Models\PromotionStack;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use RuntimeException;

trait InteractsWithPromotionStackLayers
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function populateLayerFormData(
        PromotionStack $stack,
        array $data,
    ): array {
        $stack->load([
            'layers.promotions',
            'layers.participatingOutputLayer',
            'layers.nonParticipatingOutputLayer',
        ]);

        $data['root_layer_reference'] = $stack->root_layer_reference;
        $data['layers'] = $stack->layers
            ->sortBy('sort_order')
            ->values()
            ->map(
                fn (PromotionLayer $layer): array => [
                    'reference' => $layer->reference,
                    'name' => $layer->name,
                    'promotion_ids' => $layer->promotions
                        ->pluck('id')
                        ->values()
                        ->all(),
                    'output_mode' => $this->enumValue(
                        $layer->output_mode,
                        PromotionLayerOutputMode::PassThrough->value,
                    ),
                    'participating_output_mode' => $this->enumValue(
                        $layer->participating_output_mode,
                        PromotionLayerOutputTargetMode::PassThrough->value,
                    ),
                    'participating_output_reference' => $layer->participatingOutputLayer?->reference,
                    'non_participating_output_mode' => $this->enumValue(
                        $layer->non_participating_output_mode,
                        PromotionLayerOutputTargetMode::PassThrough->value,
                    ),
                    'non_participating_output_reference' => $layer->nonParticipatingOutputLayer?->reference,
                ],
            )
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncStackGraph(PromotionStack $stack, array $data): void
    {
        $stack->update(['name' => (string) $data['name']]);

        $rows = $this->normalizeRepeaterRows($data['layers'] ?? []);
        $layerRows = $this->sanitizeLayerRows($rows);

        if ($layerRows === []) {
            throw ValidationException::withMessages([
                'data.layers' => 'At least one layer is required.',
            ]);
        }

        $this->assertReferencesAreUnique($layerRows);

        $stack->layers()->delete();

        $createdLayersByReference = [];
        $rowsByReference = [];

        foreach ($layerRows as $sortOrder => $layerRow) {
            $reference = $layerRow['reference'];
            $outputMode = $this->normalizeOutputMode(
                $layerRow['output_mode'] ?? null,
            );
            $isSplit = $outputMode === PromotionLayerOutputMode::Split->value;

            $layer = $stack->layers()->create([
                'reference' => $reference,
                'name' => $layerRow['name'],
                'sort_order' => $sortOrder,
                'output_mode' => $outputMode,
                'participating_output_mode' => $isSplit
                    ? $this->normalizeOutputTargetMode(
                        $layerRow['participating_output_mode'] ?? null,
                    )
                    : null,
                'non_participating_output_mode' => $isSplit
                    ? $this->normalizeOutputTargetMode(
                        $layerRow['non_participating_output_mode'] ?? null,
                    )
                    : null,
                'participating_output_layer_id' => null,
                'non_participating_output_layer_id' => null,
            ]);

            $promotionIds = collect($layerRow['promotion_ids'] ?? [])
                ->filter(fn (mixed $value): bool => is_numeric($value))
                ->map(fn (mixed $value): int => (int) $value)
                ->unique()
                ->values();

            $promotionPivotData = [];

            foreach ($promotionIds as $promotionSort => $promotionId) {
                $promotionPivotData[$promotionId] = [
                    'sort_order' => $promotionSort,
                ];
            }

            $layer->promotions()->sync($promotionPivotData);

            $createdLayersByReference[$reference] = $layer;
            $rowsByReference[$reference] = $layerRow;
        }

        foreach ($rowsByReference as $reference => $layerRow) {
            $layer = $createdLayersByReference[$reference];
            $outputMode = $this->normalizeOutputMode(
                $layerRow['output_mode'] ?? null,
            );

            if ($outputMode !== PromotionLayerOutputMode::Split->value) {
                continue;
            }

            $layer->update([
                'participating_output_layer_id' => $this->resolveOutputLayerId(
                    sourceReference: $reference,
                    outputMode: $layerRow['participating_output_mode'] ?? null,
                    outputReference: $layerRow[
                        'participating_output_reference'
                    ] ?? null,
                    createdLayersByReference: $createdLayersByReference,
                ),
                'non_participating_output_layer_id' => $this->resolveOutputLayerId(
                    sourceReference: $reference,
                    outputMode: $layerRow['non_participating_output_mode'] ??
                        null,
                    outputReference: $layerRow[
                        'non_participating_output_reference'
                    ] ?? null,
                    createdLayersByReference: $createdLayersByReference,
                ),
            ]);
        }

        $providedRootReference = trim(
            (string) ($data['root_layer_reference'] ?? ''),
        );
        $resolvedRootReference =
            $providedRootReference !== '' &&
            array_key_exists($providedRootReference, $createdLayersByReference)
                ? $providedRootReference
                : array_key_first($createdLayersByReference);

        $stack->update(['root_layer_reference' => $resolvedRootReference]);
    }

    protected function currentTeamId(): int
    {
        $tenant = Filament::getTenant();

        if ($tenant === null) {
            throw new RuntimeException(
                'A tenant must be selected to create promotion stacks.',
            );
        }

        return (int) $tenant->getKey();
    }

    private function normalizeOutputMode(mixed $value): string
    {
        if ($value === PromotionLayerOutputMode::Split->value) {
            return PromotionLayerOutputMode::Split->value;
        }

        return PromotionLayerOutputMode::PassThrough->value;
    }

    private function normalizeOutputTargetMode(mixed $value): string
    {
        if ($value === PromotionLayerOutputTargetMode::Layer->value) {
            return PromotionLayerOutputTargetMode::Layer->value;
        }

        return PromotionLayerOutputTargetMode::PassThrough->value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRepeaterRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_key_exists('reference', $value)) {
            return [$value];
        }

        return array_values(
            array_filter(
                array_values($value),
                fn (mixed $row): bool => is_array($row),
            ),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeLayerRows(array $rows): array
    {
        return array_values(
            array_filter(
                array_map(function (array $row): array {
                    $reference = trim((string) ($row['reference'] ?? ''));
                    $name = trim((string) ($row['name'] ?? ''));

                    if ($name === '') {
                        $name = $reference;
                    }

                    return [
                        'reference' => $reference,
                        'name' => $name,
                        'promotion_ids' => is_array(
                            $row['promotion_ids'] ?? null,
                        )
                            ? $row['promotion_ids']
                            : [],
                        'output_mode' => $row['output_mode'] ?? null,
                        'participating_output_mode' => $row['participating_output_mode'] ?? null,
                        'participating_output_reference' => $row['participating_output_reference'] ?? null,
                        'non_participating_output_mode' => $row['non_participating_output_mode'] ?? null,
                        'non_participating_output_reference' => $row['non_participating_output_reference'] ?? null,
                    ];
                }, $rows),
                fn (array $row): bool => $row['reference'] !== '',
            ),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $layerRows
     */
    private function assertReferencesAreUnique(array $layerRows): void
    {
        $seenReferences = [];

        foreach ($layerRows as $layerRow) {
            $reference = $layerRow['reference'];

            if (array_key_exists($reference, $seenReferences)) {
                throw ValidationException::withMessages([
                    'data.layers' => 'Layer references must be unique.',
                ]);
            }

            $seenReferences[$reference] = true;
        }
    }

    /**
     * @param  array<string, PromotionLayer>  $createdLayersByReference
     */
    private function resolveOutputLayerId(
        string $sourceReference,
        mixed $outputMode,
        mixed $outputReference,
        array $createdLayersByReference,
    ): ?int {
        if (
            $this->normalizeOutputTargetMode($outputMode) !==
            PromotionLayerOutputTargetMode::Layer->value
        ) {
            return null;
        }

        $targetReference = trim((string) $outputReference);

        if ($targetReference === '') {
            return null;
        }

        if (! array_key_exists($targetReference, $createdLayersByReference)) {
            throw ValidationException::withMessages([
                'data.layers' => "Layer [{$sourceReference}] links to unknown output layer [{$targetReference}].",
            ]);
        }

        return (int) $createdLayersByReference[$targetReference]->id;
    }

    private function enumValue(mixed $value, string $fallback): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $fallback;
    }
}
