<?php

namespace App\Filament\Admin\Resources\Promotions\Schemas;

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
            Section::make("Promotion Details")->schema([
                TextInput::make("name")->required(),

                Select::make("promotion_type")
                    ->label("Type")
                    ->options(["direct_discount" => "Direct Discount"])
                    ->required()
                    ->live(),

                Fieldset::make("Budget")
                    ->contained(false)
                    ->columns([
                        "default" => 1,
                        "md" => 2,
                        "xl" => 2,
                    ])
                    ->schema([
                        TextInput::make("application_budget")
                            ->label("Applications")
                            ->numeric()
                            ->minValue(1)
                            ->nullable(),

                        TextInput::make("monetary_budget")
                            ->label("Monetary")
                            ->numeric()
                            ->step(0.01)
                            ->prefix("£")
                            ->nullable(),
                    ]),
            ]),

            Section::make("Discount")
                ->visible(
                    fn(Get $get): bool => $get("promotion_type") ===
                        "direct_discount",
                )
                ->schema([
                    Select::make("discount_kind")
                        ->label("Type")
                        ->options(SimpleDiscountKind::asSelectOptions())
                        ->required()
                        ->live(),

                    TextInput::make("discount_percentage")
                        ->label("Percentage")
                        ->numeric()
                        ->suffix("%")
                        ->visible(
                            fn(Get $get): bool => $get("discount_kind") ===
                                SimpleDiscountKind::PercentageOff->value,
                        )
                        ->requiredIf(
                            "discount_kind",
                            SimpleDiscountKind::PercentageOff->value,
                        ),

                    TextInput::make("discount_amount")
                        ->label("Amount")
                        ->numeric()
                        ->prefix("£")
                        ->step(0.01)
                        ->visible(
                            fn(Get $get): bool => in_array(
                                $get("discount_kind"),
                                [
                                    SimpleDiscountKind::AmountOff->value,
                                    SimpleDiscountKind::AmountOverride->value,
                                ],
                            ),
                        )
                        ->requiredIf(
                            "discount_kind",
                            SimpleDiscountKind::AmountOff->value,
                        )
                        ->requiredIf(
                            "discount_kind",
                            SimpleDiscountKind::AmountOverride->value,
                        ),
                ]),

            Section::make("Qualification Rules")
                ->visible(fn(Get $get): bool => !empty($get("promotion_type")))
                ->schema([
                    Select::make("qualification_op")
                        ->label("Type")
                        ->options([
                            QualificationOp::And->value =>
                                "ALL rules must match",
                            QualificationOp::Or->value => "ANY rule must match",
                        ])
                        ->default(QualificationOp::And->value)
                        ->required(),

                    Repeater::make("qualification_rules")
                        ->label("Rules")
                        ->defaultItems(0)
                        ->addActionLabel("Add Rule")
                        ->schema([
                            Select::make("kind")
                                ->options([
                                    QualificationRuleKind::HasAll->value =>
                                        "Has All",
                                    QualificationRuleKind::HasAny->value =>
                                        "Has Any",
                                    QualificationRuleKind::HasNone->value =>
                                        "Has None",
                                    QualificationRuleKind::Group->value =>
                                        "Group",
                                ])
                                ->required()
                                ->live(),

                            TagsInput::make("tags")
                                ->trim()
                                ->suggestions(self::tagSuggestions())
                                ->visible(
                                    fn(Get $get): bool => $get("kind") !==
                                        QualificationRuleKind::Group->value,
                                )
                                ->requiredIf(
                                    "kind",
                                    QualificationRuleKind::HasAll->value,
                                )
                                ->requiredIf(
                                    "kind",
                                    QualificationRuleKind::HasAny->value,
                                )
                                ->requiredIf(
                                    "kind",
                                    QualificationRuleKind::HasNone->value,
                                ),

                            Select::make("group_op")
                                ->options([
                                    QualificationOp::And->value =>
                                        "ALL rules must match",
                                    QualificationOp::Or->value =>
                                        "ANY rule must match",
                                ])
                                ->visible(
                                    fn(Get $get): bool => $get("kind") ===
                                        QualificationRuleKind::Group->value,
                                )
                                ->requiredIf(
                                    "kind",
                                    QualificationRuleKind::Group->value,
                                ),

                            Repeater::make("group_rules")
                                ->defaultItems(0)
                                ->visible(
                                    fn(Get $get): bool => $get("kind") ===
                                        QualificationRuleKind::Group->value,
                                )
                                ->schema([
                                    Select::make("kind")
                                        ->options([
                                            QualificationRuleKind::HasAll
                                                ->value => "Has All",
                                            QualificationRuleKind::HasAny
                                                ->value => "Has Any",
                                            QualificationRuleKind::HasNone
                                                ->value => "Has None",
                                        ])
                                        ->required(),

                                    TagsInput::make("tags")
                                        ->trim()
                                        ->suggestions(self::tagSuggestions())
                                        ->required(),
                                ]),
                        ]),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function tagSuggestions(): array
    {
        return Tag::query()
            ->get()
            ->map(fn(Tag $tag): string => $tag->getTranslation("name", "en"))
            ->filter(fn(string $name): bool => $name !== "")
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
