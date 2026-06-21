<?php

namespace App\Filament\Resources\Messages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('role')
                    ->label('Rola')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—'),
                TextEntry::make('product_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->label())
                    ->placeholder('—'),
                TextEntry::make('rating')
                    ->label('Ocena')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->label())
                    ->placeholder('—'),
                TextEntry::make('content')
                    ->label('Treść')
                    ->columnSpanFull(),
                TextEntry::make('conversation.public_id')
                    ->label('Rozmowa')
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->label('Data')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
            ]);
    }
}
