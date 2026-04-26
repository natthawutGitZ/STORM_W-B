<?php

namespace App\Filament\Resources\Forms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                ImageColumn::make('banner_image'),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('discord_webhook_url')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_main_application')
                    ->boolean(),
                TextColumn::make('banner_position_y')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('webhook_url_staff')
                    ->searchable(),
                TextColumn::make('webhook_url_welcome')
                    ->searchable(),
                TextColumn::make('webhook_url_public')
                    ->searchable(),
                IconColumn::make('is_main_form')
                    ->boolean(),
                TextColumn::make('give_role_id')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
