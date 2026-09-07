<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceItemResource\Pages;
use App\Models\ServiceItem;
use App\Models\ServiceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceItemResource extends Resource
{
    protected static ?string $model = ServiceItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Dry Cleaning Items';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('service_category_id')
                ->label('Service Category')
                ->options(fn () => ServiceCategory::pluck('name', 'id'))
                ->required()
                ->searchable(),
            TextInput::make('name')
                ->label('Item Name')
                ->placeholder('e.g. Shirt, Trousers, Suit (2pc)')
                ->required()
                ->maxLength(150),
            TextInput::make('price_per_piece')
                ->label('Price per Piece (RM)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->prefix('RM'),
            TextInput::make('sort_order')
                ->label('Display Order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers show first in the customer app.'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Inactive items are hidden from the customer app but kept for past order history.'),
            FileUpload::make('image_path')
                ->label('Item Photo')
                ->image()
                ->disk('s3')
                // Private, not public — same S3 Block Public Access
                // reasoning as every other upload in this admin
                // (BannerResource, etc). Served through
                // PublicDocumentController::serviceItemImage, never a
                // raw S3 URL.
                ->visibility('private')
                ->maxSize(5120)
                ->maxFiles(1)
                ->storeFiles()
                ->directory('automaid/service-items'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(ServiceItem::query())
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_url')
                    ->label('')
                    ->square(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_per_piece')
                    ->label('Price (RM)')
                    ->money('MYR')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('service_category_id')
                    ->label('Category')
                    ->options(fn () => ServiceCategory::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceItems::route('/'),
            'create' => Pages\CreateServiceItem::route('/create'),
            'edit' => Pages\EditServiceItem::route('/{record}/edit'),
        ];
    }
}
