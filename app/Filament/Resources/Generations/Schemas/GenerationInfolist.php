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
                TextEntry::make('status')
                    ->label('Etap (lifecycle)')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—'),
                TextEntry::make('execution_attempt')->label('Próba wykonania')->numeric()->placeholder('—'),
                TextEntry::make('lease_expires_at')->label('Lease do')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextEntry::make('input_tokens')->label('Tokeny wejściowe')->numeric()->placeholder('—'),
                TextEntry::make('output_tokens')->label('Tokeny wyjściowe')->numeric()->placeholder('—'),
                TextEntry::make('cost')->label('Koszt (USD)')->numeric(decimalPlaces: 6)->placeholder('—'),
                TextEntry::make('attempts')
                    ->label('Próby (failover)')
                    ->state(fn ($record): array => collect($record->metadata['attempts'] ?? [])
                        ->map(fn (array $attempt): string => sprintf(
                            '%s — %s%s',
                            $attempt['provider'] ?? '?',
                            $attempt['status'] ?? '?',
                            ($attempt['fallbackable'] ?? false) ? ' (fallbackable)' : '',
                        ))
                        ->all())
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('—'),
                TextEntry::make('created_at')->label('Data')->dateTime('Y-m-d H:i')->placeholder('—'),
            ]);
    }
}
