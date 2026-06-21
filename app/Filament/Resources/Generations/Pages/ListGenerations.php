<?php

namespace App\Filament\Resources\Generations\Pages;

use App\Filament\Resources\Generations\GenerationResource;
use Filament\Resources\Pages\ListRecords;

class ListGenerations extends ListRecords
{
    protected static string $resource = GenerationResource::class;
}
