<?php

namespace App\Filament\Resources\Generations\Tables;

use App\Enums\InfraStatus;
use App\Enums\ResponseType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GenerationsTable
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
                TextColumn::make('model')
                    ->label('Model')
                    ->searchable(),
                TextColumn::make('response_type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                    ->color(fn ($state): string => $state?->color() ?? 'gray'),
                TextColumn::make('infra_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                    ->color(fn ($state): string => $state?->color() ?? 'gray'),
                TextColumn::make('input_tokens')
                    ->label('Wej.')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('output_tokens')
                    ->label('Wyj.')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('Koszt (USD)')
                    ->numeric(decimalPlaces: 6)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('response_type')->label('Typ')->options(ResponseType::options()),
                SelectFilter::make('infra_status')->label('Status')->options(InfraStatus::options()),
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
