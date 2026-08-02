<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WaitingListResource\Pages;
use App\Filament\Resources\WaitingListResource\RelationManagers;
use App\Models\WaitingList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaitingListResource extends Resource
{
    protected static ?string $model = WaitingList::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 8;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('waiting_lists')
                    ->groupBy(
                        'name',
                        'mobile_no',
                        'postcode'
                    );
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(), 
                TextColumn::make('email')
                    ->label('Email')
                    ->sortable(), 
                TextColumn::make('mobile_no')
                    ->label('Mobile No')
                    ->sortable(), 
                TextColumn::make('city.name')
                    ->label('City')
                    ->sortable(), 
                TextColumn::make('postcode')
                    ->label('Postcode')
                    ->sortable(), 
            ])
            ->defaultSort('id', 'desc')            
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('exportCsv')
                    ->label('Export to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($records): StreamedResponse {

                        abort_if($records->isEmpty(), 403);

                        $fileName = 'waiting-list-' . now()->format('YmdHis') . '.csv';

                        return response()->streamDownload(function () use ($records) {

                            $handle = fopen('php://output', 'w');

                            // CSV header
                            fputcsv($handle, ['Name', 'Email', 'Mobile No', 'City', 'Postcode']);

                            foreach ($records as $row) {
                                fputcsv($handle, [
                                    $row->name ?? null,
                                    $row->email ?? null,
                                    $row->mobile_no ?? null,
                                    $row->city?->name ?? null,
                                    $row->postcode ?? null,
                                ]);
                            }

                            fclose($handle);

                        }, $fileName, [
                            'Content-Type' => 'text/csv',
                        ]);
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->selectable();
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
            'index' => Pages\ListWaitingLists::route('/'),
            'create' => Pages\CreateWaitingList::route('/create'),
            'edit' => Pages\EditWaitingList::route('/{record}/edit'),
        ];
    }
}
