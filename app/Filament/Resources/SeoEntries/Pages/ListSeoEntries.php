<?php

namespace App\Filament\Resources\SeoEntries\Pages;

use App\Filament\Resources\SeoEntries\SeoEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListSeoEntries extends ListRecords
{
    protected static string $resource = SeoEntryResource::class;
}
