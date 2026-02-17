<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Schemas;

use App\Enums\PromotionLayerOutputMode;
use App\Enums\PromotionLayerOutputTargetMode;
use App\Models\Promotion;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PromotionStackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Stack Details')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 2,
                ])
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),

                    Select::make('root_layer_reference')
                        ->label('Root Layer')
                        ->options(
                            fn (Get $get): array => self::layerReferenceOptions(
                                $get('layers'),
                            ),
                        )
                        ->required()
                        ->searchable()
                        ->helperText(
                            'The layer that should be used as the stack root.',
                        ),
                ]),

            Section::make('Layers')
                ->columnSpanFull()
                ->description(
                    'Each layer can pass through or split to participating/non-participating outputs.',
                )
                ->schema([
                    Repeater::make('layers')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel('Add Layer')
                        ->reorderableWithButtons()
                        ->itemLabel(
                            fn (array $state): ?string => $state['name'] ??
                                ($state['reference'] ?? null),
                        )
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 2,
                        ])
                        ->schema([
                            TextInput::make('reference')
                                ->label('Reference')
                                ->alphaDash()
                                ->maxLength(64)
                                ->required()
                                ->live(onBlur: true)
                                ->helperText(
                                    'Unique key used for output links.',
                                ),

                            TextInput::make('name')->required()->maxLength(255),

                            Select::make('promotion_ids')
                                ->label('Promotions')
                                ->multiple()
                                ->options(
                                    fn (): array => self::promotionOptions(),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('output_mode')
                                ->label('Output')
                                ->options(
                                    PromotionLayerOutputMode::asSelectOptions(),
                                )
                                ->default(
                                    PromotionLayerOutputMode::PassThrough
                                        ->value,
                                )
                                ->required()
                                ->live(),

                            Fieldset::make('Split Outputs')
                                ->columnSpanFull()
                                ->columns([
                                    'default' => 1,
                                    'md' => 1,
                                    'xl' => 1,
                                ])
                                ->visible(
                                    fn (Get $get): bool => $get(
                                        'output_mode',
                                    ) ===
                                        PromotionLayerOutputMode::Split->value,
                                )
                                ->schema([
                                    Fieldset::make(
                                        'Participating Items',
                                    )->schema([
                                        Select::make(
                                            'participating_output_mode',
                                        )
                                            ->label('Output Type')
                                            ->options(
                                                PromotionLayerOutputTargetMode::asSelectOptions(),
                                            )
                                            ->default(
                                                PromotionLayerOutputTargetMode::PassThrough->value,
                                            )
                                            ->requiredIf(
                                                'output_mode',
                                                PromotionLayerOutputMode::Split
                                                    ->value,
                                            )
                                            ->live(),

                                        Select::make(
                                            'participating_output_reference',
                                        )
                                            ->label('Target Layer')
                                            ->options(
                                                fn (
                                                    Get $get,
                                                ): array => self::layerReferenceOptions(
                                                    $get('../../layers'),
                                                    (string) ($get(
                                                        'reference',
                                                    ) ?? ''),
                                                ),
                                            )
                                            ->visible(
                                                fn (Get $get): bool => $get(
                                                    'participating_output_mode',
                                                ) ===
                                                    PromotionLayerOutputTargetMode::Layer->value,
                                            )
                                            ->requiredIf(
                                                'participating_output_mode',
                                                PromotionLayerOutputTargetMode::Layer->value,
                                            )
                                            ->searchable(),
                                    ]),

                                    Fieldset::make(
                                        'Non Participating Items',
                                    )->schema([
                                        Select::make(
                                            'non_participating_output_mode',
                                        )
                                            ->label('Output Type')
                                            ->options(
                                                PromotionLayerOutputTargetMode::asSelectOptions(),
                                            )
                                            ->default(
                                                PromotionLayerOutputTargetMode::PassThrough->value,
                                            )
                                            ->requiredIf(
                                                'output_mode',
                                                PromotionLayerOutputMode::Split
                                                    ->value,
                                            )
                                            ->live(),

                                        Select::make(
                                            'non_participating_output_reference',
                                        )
                                            ->label('Target Layer')
                                            ->options(
                                                fn (
                                                    Get $get,
                                                ): array => self::layerReferenceOptions(
                                                    $get('../../layers'),
                                                    (string) ($get(
                                                        'reference',
                                                    ) ?? ''),
                                                ),
                                            )
                                            ->visible(
                                                fn (Get $get): bool => $get(
                                                    'non_participating_output_mode',
                                                ) ===
                                                    PromotionLayerOutputTargetMode::Layer->value,
                                            )
                                            ->requiredIf(
                                                'non_participating_output_mode',
                                                PromotionLayerOutputTargetMode::Layer->value,
                                            )
                                            ->searchable(),
                                    ]),
                                ]),
                        ]),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function promotionOptions(): array
    {
        $tenant = Filament::getTenant();

        $query = Promotion::query()->orderBy('name');

        if ($tenant !== null) {
            $query->where('team_id', $tenant->getKey());
        }

        return $query
            ->pluck('name', 'id')
            ->mapWithKeys(
                fn (string $name, int|string $id): array => [
                    (int) $id => $name,
                ],
            )
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function layerReferenceOptions(
        mixed $rawLayers,
        ?string $excludedReference = null,
    ): array {
        $options = [];
        $layerRows = self::normalizeLayerRows($rawLayers);

        foreach ($layerRows as $layer) {
            $reference = trim((string) ($layer['reference'] ?? ''));

            if ($reference === '' || $reference === $excludedReference) {
                continue;
            }

            $name = trim((string) ($layer['name'] ?? ''));
            $label = $reference;

            if ($name !== '') {
                $label = "{$reference} - {$name}";
            }

            $options[$reference] = $label;
        }

        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeLayerRows(mixed $value): array
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
}
