<?php

namespace App\Filament\Admin\Resources\Promotions\Tables;

use App\Models\DirectDiscountPromotion;
use App\Models\MixAndMatchPromotion;
use App\Models\PositionalDiscountPromotion;
use App\Models\Promotion;
use App\Services\PromotionDiscount\PromotionDiscountFormatter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        $discountFormatter = resolve(PromotionDiscountFormatter::class);

        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'promotionable' => function (MorphTo $morphTo): void {
                        $morphTo->morphWith([
                            DirectDiscountPromotion::class => ['discount'],
                            MixAndMatchPromotion::class => ['discount'],
                            PositionalDiscountPromotion::class => ['discount'],
                        ]);
                    },
                ]),
            )
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),

                TextColumn::make('promotionable_type')
                    ->label('Type')
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            DirectDiscountPromotion::class => 'Direct Discount',
                            MixAndMatchPromotion::class => 'Mix and Match',
                            PositionalDiscountPromotion::class => 'Positional Discount',
                            default => $state,
                        },
                    ),

                TextColumn::make('discount_configuration')
                    ->label('Discount')
                    ->state(
                        fn (
                            Promotion $record,
                        ): ?string => $discountFormatter->format($record),
                    )
                    ->placeholder('Unknown'),

                TextColumn::make('application_budget')
                    ->label('App. Budget')
                    ->placeholder('Unlimited')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('monetary_budget')
                    ->label('Monetary Budget')
                    ->placeholder('Unlimited')
                    ->money()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
