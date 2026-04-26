<?php

namespace App\Filament\Resources\Forms\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                FileUpload::make('banner_image')
                    ->image(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('discord_webhook_url')
                    ->url(),
                Toggle::make('is_active'),
                Toggle::make('is_main_application'),
                TextInput::make('banner_position_y')
                    ->numeric()
                    ->default(50),
                TextInput::make('webhook_url_staff'),
                TextInput::make('webhook_url_welcome'),
                TextInput::make('webhook_url_public'),
                Toggle::make('is_main_form'),
                TextInput::make('give_role_id'),
            ]);
    }
}
