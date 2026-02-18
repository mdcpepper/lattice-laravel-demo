<?php

namespace App\Filament\Admin\Resources\BacktestedCarts\Schemas;

use App\Services\NanosecondDurationFormatter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BacktestedCartInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('cart.ulid')
                ->label('Cart ID')
                ->fontFamily('mono')
                ->placeholder('-'),

            TextEntry::make('created_at')->dateTime(),

            TextEntry::make('processing_time')
                ->label('End-to-end')
                ->formatStateUsing(
                    fn (
                        mixed $state,
                    ): string => NanosecondDurationFormatter::format(
                        is_numeric($state) ? (float) $state : null,
                    ),
                ),

            TextEntry::make('solve_time')
                ->label('Solve')
                ->formatStateUsing(
                    fn (
                        mixed $state,
                    ): string => NanosecondDurationFormatter::format(
                        is_numeric($state) ? (float) $state : null,
                    ),
                ),

            TextEntry::make('promotionStack.name')
                ->label('Stack')
                ->getStateUsing(
                    fn ($record) => $record->backtest->promotionStack->name,
                ),
        ]);
    }
}
