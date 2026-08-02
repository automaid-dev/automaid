<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Filament\Resources\SettingResource\RelationManagers;
use App\Models\Setting;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Actions\Action;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationUrl(): string
    {
        // Always redirect to edit page for record ID = 1
        return static::getUrl('edit', ['record' => 1]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
            'create-addon' => Pages\Addon\CreateAddon::route('/create-addon'),
            'edit-addon' => Pages\Addon\EditAddon::route('/{record}/edit-addon'),
            'create-voucher' => Pages\Voucher\CreateVoucher::route('/create-voucher'),
            'edit-voucher' => Pages\Voucher\EditVoucher::route('/{record}/edit-voucher'),
        ];
    }



}


