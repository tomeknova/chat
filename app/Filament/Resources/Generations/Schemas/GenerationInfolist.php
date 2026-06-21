<?php

namespace App\Filament\Resources\Generations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class GenerationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('model')->label('Model'),
                TextEntry::make('operation_id')->label('Operation ID'),
                TextEntry::make('response_type')
                    ->label('Typ odpowiedzi')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->label())
                    ->placeholder('—'),
                TextEntry::make('infra_status')
                    ->label('Status techniczny')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—'),
                TextEntry::make('input_tokens')->label('Tokeny wejściowe')->numeric()->placeholder('—'),
                TextEntry::make('output_tokens')->label('Tokeny wyjściowe')->numeric()->placeholder('—'),
                TextEntry::make('cost')->label('Koszt (USD)')->numeric(decimalPlaces: 6)->placeholder('—'),
                TextEntry::make('created_at')->label('Data')->dateTime('Y-m-d H:i')->placeholder('—'),
            ]);
    }
}
