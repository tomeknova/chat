<?php

namespace App\Filament\Resources\Generations;

use App\Filament\Resources\Generations\Pages\ListGenerations;
use App\Filament\Resources\Generations\Pages\ViewGeneration;
use App\Filament\Resources\Generations\Schemas\GenerationInfolist;
use App\Filament\Resources\Generations\Tables\GenerationsTable;
use App\Models\Generation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only telemetry for AI generations (cost, tokens, response/infra status).
 * Supports cost monitoring behind the daily-budget safeguard.
 */
class GenerationResource extends Resource
{
    protected static ?string $model = Generation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Asystent';
    }

    public static function getNavigationLabel(): string
    {
        return 'Generacje (telemetria)';
    }

    public static function getModelLabel(): string
    {
        return 'generacja';
    }

    public static function getPluralModelLabel(): string
    {
        return 'generacje';
    }

    public static function infolist(Schema $schema): Schema
    {
        return GenerationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GenerationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGenerations::route('/'),
            'view' => ViewGeneration::route('/{record}'),
        ];
    }
}
