<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    /**
     * [canCreate description]
     * @return [type] [description]
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('id')
                    ->label('Subscription ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Name')
                    ->default('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => ucfirst(strtolower($state)))
                    ->color(fn(string $state): string => match (strtolower($state)) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        'inactive' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);

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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
            'view' => Pages\ViewSubscription::route('/{record}/{user}/view'), 
        ];
    }


}
