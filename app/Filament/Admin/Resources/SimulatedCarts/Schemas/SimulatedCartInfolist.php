<?php

namespace App\Filament\Admin\Resources\SimulatedCarts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SimulatedCartInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('ulid')
                    ->label('ID')
                    ->fontFamily('mono'),

                TextEntry::make('promotionStack.name')
                    ->label('Stack')
                    ->getStateUsing(fn ($record) => $record->simulationRun->promotionStack->name),

                TextEntry::make('customer.name')
                    ->label('Customer')
                    ->placeholder('—'),

                TextEntry::make('email')
                    ->placeholder('—'),

                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
