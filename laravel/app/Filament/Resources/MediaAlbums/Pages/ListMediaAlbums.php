<?php

namespace App\Filament\Resources\MediaAlbums\Pages;

use App\Filament\Resources\MediaAlbums\MediaAlbumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMediaAlbums extends ListRecords
{
    protected static string $resource = MediaAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
