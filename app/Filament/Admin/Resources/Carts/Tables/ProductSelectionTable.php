<?php

namespace App\Filament\Admin\Resources\Carts\Tables;

use App\Models\Category;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms\Components\TableSelect\Livewire\TableSelectLivewireComponent;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductSelectionTable
{
    public static function configure(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->getKey();

        $livewireRecordCategoryId = null;
        $tableLivewire = $table->getLivewire();

        if (
            $tableLivewire instanceof TableSelectLivewireComponent &&
            $tableLivewire->record instanceof Category
        ) {
            $livewireRecordCategoryId = (int) $tableLivewire->record->getKey();
        }

        $categoryId = static::resolveCategoryId(
            $table->getArguments()['category_id'] ?? null,
            $livewireRecordCategoryId,
        );

        return $table
            ->query(fn (): Builder => Product::query())
            ->modifyQueryUsing(function (Builder $query) use (
                $tenantId,
                $categoryId,
            ): Builder {
                if (is_numeric($tenantId)) {
                    $query->where('team_id', (int) $tenantId);
                }

                if (is_numeric($categoryId)) {
                    $query->where('category_id', (int) $categoryId);
                }

                return $query;
            })
            ->columns([
                ImageColumn::make('thumb_url')->label('Image'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category.name')->searchable()->sortable(),
                TextColumn::make('price')->money()->sortable(),
                TextColumn::make('stock')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    private static function resolveCategoryId(
        mixed $categoryId,
        ?int $fallbackCategoryId,
    ): ?int {
        if (is_numeric($categoryId)) {
            return (int) $categoryId;
        }

        return $fallbackCategoryId;
    }
}
