<?php

namespace App\Filament\Resources\MediaAlbums\Pages;

use App\Filament\Resources\MediaAlbums\MediaAlbumResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMediaAlbum extends EditRecord
{
    protected static string $resource = MediaAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
