<?php

namespace App\Filament\Resources\QrcodeResource\Pages;

use App\Filament\Resources\QrcodeResource;
use Filament\Resources\Pages\EditRecord;
use App\Models\Qrcode as Qrcode2;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\User;
use Carbon\Carbon;

class EditQrcode extends EditRecord
{
    protected static string $resource = QrcodeResource::class;

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return $this->record->series_no;
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return $this->record->series_no;        
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return []; // This removes all default form actions (Create & Cancel buttons)
    }

    /**
     * [mutateFormDataBeforeUpdate description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    // protected function mutateFormDataBeforeUpdate(array $data): array
    // {
    //     $data['scan_by'] = $data['user_id'];
    //     $data['scan_at'] = Carbon::now();
    //     $data['updated_by'] = auth()->user()->id();
    //     return $data;
    // }

    /**
     * [handleRecordUpdate description]
     * @param  Model  $record [description]
     * @param  array  $data   [description]
     * @return [type]         [description]
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['status'] = Qrcode2::SCANNED; // added 2026-02-21
        $data['scan_by'] = auth()->id(); 
        $data['scan_at'] = now();
        $record->update($data);
        return $record;
    }

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
        // dd($this->record->series_no);
        return $form->schema([
            Grid::make(3)
                ->schema([

                    // Left Column
                    Grid::make()
                        ->schema([

                            Section::make()
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('display_scan_by')->disableLabel(true)
                                                ->content('Scan By: User has not yet scanned the QR code.')->columnSpanFull()
                                                ->visible(fn ($get) => $get('user_id') === null),
                                            Placeholder::make('display_scan_by')->disableLabel(true)
                                                ->content('Scanned by:')
                                                ->visible(fn ($get) => $get('user_id') !== null),
                                            Placeholder::make('display_scan_by_value')->disableLabel(true)
                                                ->content(fn ($record) => $record?->user->name ?? '-')
                                                ->visible(fn ($get) => $get('user_id') !== null)
                                                ->extraAttributes(['class' => 'text-right']),

                                            Placeholder::make('display_scan_at')->disableLabel(true)
                                                ->content('Scan At: User has not yet scanned the QR code.')->columnSpanFull()
                                                ->visible(fn ($get) => $get('user_id') === null),
                                            Placeholder::make('display_scan_at')->disableLabel(true)
                                                ->content('Scanned on:')
                                                ->visible(fn ($get) => $get('user_id') !== null),
                                            Placeholder::make('display_scan_at_value')->disableLabel(true)
                                                ->content(fn ($record) => $record?->scan_at 
                                                ? Carbon::parse($record->scan_at)->format('d M Y, h:i a') 
                                                : '-')
                                                ->visible(fn ($get) => $get('user_id') !== null)
                                                ->extraAttributes(['class' => 'text-right']),
                                        ]),
                                ]), 

                            Section::make()
                                ->schema([
                                    Grid::make(1)
                                        ->schema([
                                            Select::make('user_id')
                                                ->label('Assign to another user')
                                                ->options(User::role('customer')->pluck('name', 'id'))
                                                ->searchable() // Enables search inside the dropdown
                                                ->preload(), // Preload options for better UX
                                            Placeholder::make('hint_text')->label(false)->content('Use this feature to assign the QR code to a different user if needed'),
                                        ]),
                                ])
                                ->footerActions([
                                    Action::make('update')
                                        ->label('Update')
                                        ->submit('edit'), 
                                ]),

                        ])->columnSpan(2),

                    // Right Column
                    Grid::make()
                        ->schema([
                            Section::make()
                                ->schema([
                                    Placeholder::make('display_qrcode')
                                        ->label(false)
                                        ->content(QrCode::errorCorrection('H')
                                            ->style('round')
                                            ->size(200)
                                            ->generate($this->record->series_no))
                                        ->columnSpanFull(),
                                    Placeholder::make('display_code')
                                        ->label(false)
                                        ->content($this->record->series_no)
                                        ->columnSpanFull(),
                                ])
                                ->key('qrcodeSection')
                                ->footerActions([
                                    Action::make('download_qrcode')
                                        ->label('Download QR Code')
                                        ->icon('heroicon-m-arrow-down-tray')
                                        ->url(fn ($record) => route('qrcode.print', $record->series_no))
                                        ->openUrlInNewTab(), 
                                ]),

                        ])->columnSpan(1),
                ]),

        ]);
    }

}
