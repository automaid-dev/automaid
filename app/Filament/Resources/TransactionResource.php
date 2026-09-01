<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('created_at')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state->format('d M Y'))                    
                    ->label('Date'),
                TextColumn::make('id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(function (string $state): string {
                        $labels = [
                            Transaction::PURCHASE_BAG => 'BAG PURCHASE',
                            Transaction::SUBSCRIPTION => 'SUBSCRIPTION',
                            Transaction::SUBSCRIPTION_RENEWAL => 'SUBSCRIPTION RENEWAL',
                            Transaction::BOOKING => 'BOOKING',
                        ];
                        return $labels[$state] ?? strtoupper(str_replace('_', ' ', $state));
                    }),
                TextColumn::make('amount')
                    ->label('Total Amount (RM)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'primary',
                        'refunded' => 'danger',
                        default => 'gray',
                    })
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        Transaction::BOOKING => 'BOOKING',
                        Transaction::SUBSCRIPTION => 'SUBSCRIPTION',
                        Transaction::SUBSCRIPTION_RENEWAL => 'SUBSCRIPTION RENEWAL',
                        Transaction::PURCHASE_BAG => 'BAG PURCHASE',
                    ])
                    ->placeholder('All Types')
                    ->label(false),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('date')->label(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date'], fn ($q) => $q->whereDate('created_at', $data['date']));
                    })
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->icon('heroicon-o-magnifying-glass')
                    ->url(fn ($record) => \App\Filament\Resources\TransactionResource\Pages\ViewTransaction::getUrl(['record' => $record->getKey()]))
                    ->openUrlInNewTab(false)
                    ->modal(false),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
            'view' => Pages\ViewTransaction::route('/{record}/view'),
        ];
    }


}

