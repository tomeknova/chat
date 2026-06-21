<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\MessageRole;
use App\Enums\ProductStatus;
use App\Enums\Rating;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Rola')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                    ->color(fn ($state): string => $state?->color() ?? 'gray'),
                TextColumn::make('content')
                    ->label('Treść')
                    ->limit(70)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('product_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                    ->color(fn ($state): string => $state?->color() ?? 'gray'),
                TextColumn::make('rating')
                    ->label('Ocena')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                    ->color(fn ($state): string => $state?->color() ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rola')->options(MessageRole::options()),
                SelectFilter::make('rating')->label('Ocena')->options(Rating::options()),
                SelectFilter::make('product_status')->label('Status')->options(ProductStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
