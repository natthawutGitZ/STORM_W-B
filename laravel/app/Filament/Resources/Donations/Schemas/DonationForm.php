<?php

namespace App\Filament\Resources\Donations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DonationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('donor_name')
                    ->default('Anonymous'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Textarea::make('message')
                    ->columnSpanFull(),
                FileUpload::make('slip_image')
                    ->image(),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                    ->default('pending'),
                TextInput::make('admin_note'),
                TextInput::make('ip_address'),
                DateTimePicker::make('reviewed_at'),
                TextInput::make('reviewed_by'),
                TextInput::make('transaction_ref'),
                TextInput::make('verify_status'),
                Textarea::make('verify_data')
                    ->columnSpanFull(),
            ]);
    }
}
