<?php

namespace App\Filament\Admin\Resources\Promotions\Tables;

use App\Models\DirectDiscountPromotion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("name")->searchable()->sortable(),

                TextColumn::make("promotionable_type")
                    ->label("Type")
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            DirectDiscountPromotion::class => "Direct Discount",
                            default => $state,
                        },
                    ),

                TextColumn::make("application_budget")
                    ->label("App. Budget")
                    ->placeholder("Unlimited")
                    ->numeric()
                    ->sortable(),

                TextColumn::make("monetary_budget")
                    ->label("Monetary Budget")
                    ->placeholder("Unlimited")
                    ->money()
                    ->sortable(),

                TextColumn::make("created_at")
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
