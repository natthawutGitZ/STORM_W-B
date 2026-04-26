<?php

namespace App\Filament\Resources\Ranks;

use App\Filament\Resources\Ranks\Pages\CreateRank;
use App\Filament\Resources\Ranks\Pages\EditRank;
use App\Filament\Resources\Ranks\Pages\ListRanks;
use App\Filament\Resources\Ranks\Schemas\RankForm;
use App\Filament\Resources\Ranks\Tables\RanksTable;
use App\Models\Rank;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RankResource extends Resource
{
    protected static ?string $model = Rank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return RankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RanksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRanks::route('/'),
            'create' => CreateRank::route('/create'),
            'edit' => EditRank::route('/{record}/edit'),
        ];
    }
}
