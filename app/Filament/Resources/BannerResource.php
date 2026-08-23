<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Filament\Resources\BannerResource\RelationManagers;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 7;

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public static function form(Form $form): Form
    {
        return $form;
    }

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public static function table(Table $table): Table
    {
        return $table
            ->query(Banner::query())
            // Filament's built-in drag-handle reordering — matches the
            // "Drag to change order" behaviour from the reference
            // screenshot, updating sort_order automatically as rows
            // are dragged, rather than needing a manual order field.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_url')
                    ->label('')
                    ->square(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('target')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Banner::TARGET_CUSTOMER => 'info',
                        Banner::TARGET_MERCHANTRIDER => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Banner::TARGET_CUSTOMER => 'Customer app',
                        Banner::TARGET_MERCHANTRIDER => 'Merchant/Rider app',
                    })
                    ->sortable(),
                TextColumn::make('link')
                    ->label('Link')
                    ->limit(40)
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state->format('d M Y')),
            ])
            ->filters([
                SelectFilter::make('target')
                    ->options([
                        Banner::TARGET_CUSTOMER => 'Customer app',
                        Banner::TARGET_MERCHANTRIDER => 'Merchant/Rider app',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100]);
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
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
