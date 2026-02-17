<?php

namespace App\Filament\Admin\Resources\Promotions\Schemas;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\PromotionType;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Spatie\Tags\Tag;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Promotion Details')->schema([
                TextInput::make('name')->required(),

                Select::make('promotion_type')
                    ->label('Type')
                    ->options(PromotionType::asSelectOptions())
                    ->required()
                    ->live(),

                TextInput::make('size')
                    ->label('Size')
                    ->numeric()
                    ->minValue(1)
                    ->step(1)
                    ->live()
                    ->visible(
                        fn (Get $get): bool => $get('promotion_type') ==
                            PromotionType::PositionalDiscount->value,
                    )
                    ->required(),

                Fieldset::make('Budget')
                    ->contained(false)
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 2,
                    ])
                    ->schema([
                        TextInput::make('application_budget')
                            ->label('Applications')
                            ->numeric()
                            ->minValue(1)
                            ->step(1)
                            ->nullable(),

                        TextInput::make('monetary_budget')
                            ->label('Monetary')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('£')
                            ->nullable(),
                    ]),
            ]),

            static::directDiscountDetails(),
            static::mixAndMatchDetails(),
            static::mixAndMatchSlots(),
            static::positionalDiscountPositions(),

            Section::make('Qualification Rules')
                ->visible(
                    fn (
                        Get $get,
                    ): bool => PromotionType::hasPromotionQualification(
                        $get('promotion_type'),
                    ),
                )
                ->schema(static::qualifications()),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function qualifications(): array
    {
        return [
            Select::make('qualification_op')
                ->label('Type')
                ->options(QualificationOp::asSelectOptions())
                ->default(QualificationOp::And->value)
                ->required(),

            Repeater::make('qualification_rules')
                ->label('Rules')
                ->defaultItems(0)
                ->addActionLabel('Add Rule')
                ->schema([
                    Select::make('kind')
                        ->options(QualificationRuleKind::asSelectOptions())
                        ->required()
                        ->live(),

                    TagsInput::make('tags')
                        ->trim()
                        ->suggestions(self::tagSuggestions())
                        ->visible(
                            fn (Get $get): bool => $get('kind') !==
                                QualificationRuleKind::Group->value,
                        )
                        ->requiredIf(
                            'kind',
                            QualificationRuleKind::HasAll->value,
                        )
                        ->requiredIf(
                            'kind',
                            QualificationRuleKind::HasAny->value,
                        )
                        ->requiredIf(
                            'kind',
                            QualificationRuleKind::HasNone->value,
                        ),

                    Select::make('group_op')
                        ->options(QualificationOp::asSelectOptions())
                        ->visible(
                            fn (Get $get): bool => $get('kind') ===
                                QualificationRuleKind::Group->value,
                        )
                        ->requiredIf(
                            'kind',
                            QualificationRuleKind::Group->value,
                        ),

                    Repeater::make('group_rules')
                        ->defaultItems(0)
                        ->visible(
                            fn (Get $get): bool => $get('kind') ===
                                QualificationRuleKind::Group->value,
                        )
                        ->schema([
                            Select::make('kind')
                                ->options(
                                    QualificationRuleKind::asSelectOptions(
                                        false,
                                    ),
                                )
                                ->required(),

                            TagsInput::make('tags')
                                ->trim()
                                ->suggestions(self::tagSuggestions())
                                ->required(),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function tagSuggestions(): array
    {
        return Tag::query()
            ->get()
            ->map(fn (Tag $tag): string => $tag->getTranslation('name', 'en'))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function directDiscountDetails(): Section
    {
        return Section::make('Discount')
            ->visible(
                fn (Get $get): bool => in_array(
                    $get('promotion_type'),
                    [
                        PromotionType::DirectDiscount->value,
                        PromotionType::PositionalDiscount->value,
                    ],
                    true,
                ),
            )
            ->schema([
                Select::make('discount_kind')
                    ->label('Type')
                    ->options(SimpleDiscountKind::asSelectOptions())
                    ->required()
                    ->live(),

                TextInput::make('discount_percentage')
                    ->label('Percentage')
                    ->numeric()
                    ->suffix('%')
                    ->visible(
                        fn (Get $get): bool => $get('discount_kind') ===
                            SimpleDiscountKind::PercentageOff->value,
                    )
                    ->requiredIf(
                        'discount_kind',
                        SimpleDiscountKind::PercentageOff->value,
                    ),

                TextInput::make('discount_amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('£')
                    ->step(0.01)
                    ->visible(
                        fn (Get $get): bool => in_array($get('discount_kind'), [
                            SimpleDiscountKind::AmountOff->value,
                            SimpleDiscountKind::AmountOverride->value,
                        ]),
                    )
                    ->requiredIf(
                        'discount_kind',
                        SimpleDiscountKind::AmountOff->value,
                    )
                    ->requiredIf(
                        'discount_kind',
                        SimpleDiscountKind::AmountOverride->value,
                    ),
            ]);
    }

    private static function mixAndMatchDetails(): Section
    {
        return Section::make('Discount')
            ->visible(
                fn (Get $get): bool => $get('promotion_type') ===
                    PromotionType::MixAndMatch->value,
            )
            ->schema([
                Select::make('discount_kind')
                    ->label('Type')
                    ->options(MixAndMatchDiscountKind::asSelectOptions())
                    ->required()
                    ->live(),

                TextInput::make('discount_percentage')
                    ->label('Percentage')
                    ->numeric()
                    ->suffix('%')
                    ->visible(
                        fn (Get $get): bool => \in_array(
                            $get('discount_kind'),
                            MixAndMatchDiscountKind::percentageTypes(),
                        ),
                    )
                    ->requiredIf(
                        'discount_kind',
                        MixAndMatchDiscountKind::PercentageOffAllItems->value,
                    )
                    ->requiredIf(
                        'discount_kind',
                        MixAndMatchDiscountKind::PercentageOffCheapest->value,
                    ),

                TextInput::make('discount_amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('£')
                    ->step(0.01)
                    ->visible(
                        fn (Get $get): bool => \in_array(
                            $get('discount_kind'),
                            MixAndMatchDiscountKind::amountTypes(),
                        ),
                    )
                    ->requiredIf(
                        'discount_kind',
                        MixAndMatchDiscountKind::AmountOffEachItem->value,
                    )
                    ->requiredIf(
                        'discount_kind',
                        MixAndMatchDiscountKind::AmountOffTotal->value,
                    )
                    ->requiredIf(
                        'discount_kind',
                        MixAndMatchDiscountKind::OverrideEachItem->value,
                    )
                    ->requiredIf(
                        'discount_kind',
                        MixAndMatchDiscountKind::OverrideTotal->value,
                    ),
            ]);
    }

    private static function mixAndMatchSlots(): Section
    {
        return Section::make('Slots')
            ->visible(
                fn (Get $get): bool => PromotionType::MixAndMatch->value ===
                    $get('promotion_type'),
            )
            ->schema([
                Repeater::make('slots')
                    ->hiddenLabel('Slots')
                    ->defaultItems(0)
                    ->addActionLabel('Add Slot')
                    ->schema([
                        Fieldset::make('Items')
                            ->contained(false)
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 2,
                            ])
                            ->schema([
                                TextInput::make('min')
                                    ->label('Minimum')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),

                                TextInput::make('max')
                                    ->label('Maximum')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                            ]),
                        ...static::qualifications(),
                    ]),
            ]);
    }

    private static function positionalDiscountPositions(): Section
    {
        return Section::make('Positions')
            ->visible(
                fn (Get $get): bool => PromotionType::PositionalDiscount
                    ->value === $get('promotion_type'),
            )
            ->schema([
                Repeater::make('positions')
                    ->hiddenLabel('Positions')
                    ->defaultItems(0)
                    ->addActionLabel('Add Position')
                    ->schema([
                        Select::make('position')
                            ->label('Position')
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->options(
                                fn (Get $get): array => self::positionOptions(
                                    $get('../../size'),
                                ),
                            )
                            ->in(
                                fn (Get $get): array => array_keys(
                                    self::positionOptions($get('../../size')),
                                ),
                            )
                            ->required(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function positionOptions(mixed $size): array
    {
        if (! is_numeric($size)) {
            return [];
        }

        $size = (int) $size;

        if ($size < 1) {
            return [];
        }

        $options = [];

        foreach (range(1, $size) as $position) {
            $options[$position] = (string) $position;
        }

        return $options;
    }
}
