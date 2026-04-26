<?php

namespace App\Filament\Resources\Ranks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('abbreviation')
                    ->required(),
                TextInput::make('nato_code'),
                TextInput::make('category')
                    ->required(),
                FileUpload::make('image')
                    ->image()
                    ->required(),
                TextInput::make('order_index')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
