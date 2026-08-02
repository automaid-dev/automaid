<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QrcodeResource\Pages;
use App\Filament\Resources\QrcodeResource\RelationManagers;
use App\Models\Qrcode as Qrcode2;
use Filament\Forms;
// use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\HTML;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Filament\Tables\Actions\Action;
use Carbon\Carbon;

class QrcodeResource extends Resource
{
    protected static ?string $model = Qrcode2::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 4;

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
     * [getRelations description]
     * @return [type] [description]
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * [getPages description]
     * @return [type] [description]
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQrcodes::route('/'),
            'create' => Pages\CreateQrcode::route('/create'),
            'edit' => Pages\EditQrcode::route('/{record}/edit'),
        ];
    }

    /**
     * [getNavigationLabel description]
     * @return [type] [description]
     */
    public static function getNavigationLabel(): string
    {
        return 'QR Code';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public static function getBreadcrumb(): string
    {
        return 'QR Code';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->rowIndex()
                    ->label('No') 
                    ->sortable(), 
                TextColumn::make('series_no')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('j M Y, h:i a'))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Scanned By')
                    ->default('-')
                    // ->formatStateUsing(fn ($state) => $state ?? 'N/A')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'danger',
                        'scanned' => 'info',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'PENDING',
                        'scanned' => 'SCANNED',
                    })
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('download_qrcode')
                    ->label('')
                    ->icon('heroicon-m-arrow-down-tray')                    
                    ->url(fn ($record) => route('qrcode.print', $record->series_no))
                    ->openUrlInNewTab(), 

                
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);

    }

}
