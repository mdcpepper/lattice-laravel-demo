<?php

namespace App\Filament\Admin\Resources\Promotions\Tables;

use App\Models\Cart\CartItem;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\MixAndMatchPromotion;
use App\Models\Promotions\PositionalDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\TieredThresholdPromotion;
use App\Services\PromotionDiscount\PromotionDiscountFormatter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        $discountFormatter = resolve(PromotionDiscountFormatter::class);

        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with([
                        'promotionable' => function (MorphTo $morphTo): void {
                            $morphTo->morphWith([
                                DirectDiscountPromotion::class => ['discount'],
                                MixAndMatchPromotion::class => ['discount'],
                                PositionalDiscountPromotion::class => [
                                    'discount',
                                ],
                                TieredThresholdPromotion::class => [
                                    'tiers.discount',
                                ],
                            ]);
                        },
                    ])
                    ->withCount('redemptions')
                    ->addSelect([
                        'monetary_redeemed' => DB::table(
                            'promotion_redemptions',
                        )
                            ->selectRaw(
                                'COALESCE(SUM(original_price - final_price), 0)',
                            )
                            ->whereColumn('promotion_id', 'promotions.id')
                            ->where('redeemable_type', CartItem::class),
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
                            TieredThresholdPromotion::class => 'Tiered Threshold',
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
                    ->label('Redemption Budget')
                    ->state(
                        fn (
                            Promotion $record,
                        ): string => ($record->redemptions_count ?? 0).
                            ' / '.
                            ($record->application_budget ?? '∞'),
                    )
                    ->sortable(),

                TextColumn::make('monetary_budget')
                    ->label('Monetary Budget')
                    ->state(function (Promotion $record): string {
                        $redeemed = money(
                            (int) ($record->monetary_redeemed ?? 0),
                            'GBP',
                        )->format();
                        $rawBudget = $record->getRawOriginal('monetary_budget');
                        $budget =
                            $rawBudget !== null
                                ? money((int) $rawBudget, 'GBP')->format()
                                : '∞';

                        return "{$redeemed} / {$budget}";
                    })
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
