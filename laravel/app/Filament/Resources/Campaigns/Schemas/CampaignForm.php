<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                FileUpload::make('image_path')
                    ->image(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(['Active' => 'Active', 'Completed' => 'Completed'])
                    ->default('Active'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
