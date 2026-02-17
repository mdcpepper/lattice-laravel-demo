<?php

namespace App\Filament\Admin\Resources\Carts\Tables;

use App\Models\Product;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductSelectionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn() => Product::query())
            ->columns([
                ImageColumn::make("thumb_url")->label("Image"),
                TextColumn::make("name")->searchable()->sortable(),
                TextColumn::make("category.name")->searchable()->sortable(),
                TextColumn::make("price")->money()->sortable(),
                TextColumn::make("stock")->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make("category")
                    ->relationship("category", "name")
                    ->searchable()
                    ->preload(),
            ]);
    }
}
