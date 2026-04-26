<?php

namespace App\Filament\Resources\MediaAlbums\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MediaAlbumForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('category_id')
                    ->numeric(),
                FileUpload::make('cover_image_id')
                    ->image(),
                Select::make('status')
                    ->options(['published' => 'Published', 'draft' => 'Draft', 'archived' => 'Archived'])
                    ->default('published'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
