<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('steamid'),
                TextInput::make('username'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('google_id'),
                TextInput::make('password')
                    ->password(),
                TextInput::make('generated_password')
                    ->password(),
                TextInput::make('personaname')
                    ->required(),
                TextInput::make('avatar')
                    ->default('assets/images/default_avatar.png'),
                TextInput::make('profileurl'),
                Select::make('role')
                    ->options(['user' => 'User', 'admin' => 'Admin'])
                    ->default('user')
                    ->required(),
                TextInput::make('rank')
                    ->default('Recruit'),
                TextInput::make('position'),
                Select::make('status')
                    ->options(['Active' => 'Active', 'Inactive' => 'Inactive', 'LOA' => 'L o a'])
                    ->default('Active')
                    ->required(),
                DateTimePicker::make('join_date')
                    ->required(),
                Textarea::make('resume_data')
                    ->columnSpanFull(),
                TextInput::make('parent_id')
                    ->numeric(),
                TextInput::make('coc_sort_order')
                    ->numeric(),
                TextInput::make('coc_x')
                    ->numeric()
                    ->default(0),
                TextInput::make('coc_y')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
