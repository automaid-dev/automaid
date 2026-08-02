<?php

namespace App\Filament\Resources\QrcodeResource\Pages;

use App\Filament\Resources\QrcodeResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Qrcode;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;

class CreateQrcode extends CreateRecord
{
    protected static string $resource = QrcodeResource::class;

    protected static bool $canCreateAnother = false;
    
    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'Generate QR Code'; // Custom title
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return 'Generate QR Code'; // Custom title        
    }

    /**
     * [getFormActions description]
     * @return [type] [description]
     */
    protected function getFormActions(): array
    {
        return []; // This removes all default form actions (Create & Cancel buttons)
    }

    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    // protected function getRedirectUrl(): string
    // {
    //     return route('filament.admin.resources.qrcodes.index'); // Redirect to dashboard after submit
    // }

    protected function handleRecordCreation(array $data): Qrcode
    {
        $qr = new Qrcode();
        $code = $qr->getNextSeriesNo();
        $data['series_no'] = $code;
        $data['type'] = Qrcode::MANUAL;
        $data['status'] = Qrcode::SCANNED;
        // $data['status'] = Qrcode::PENDING;
        $data['created_by'] = auth()->user()->id;
        return Qrcode::create($data);
    }

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
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
                                                ->content('Scan By: User has not yet scanned the QR code.')->columnSpanFull(),
                                            Placeholder::make('display_scan_at')->disableLabel(true)
                                                ->content('Scan At: User has not yet scanned the QR code.')->columnSpanFull(),
                                        ]),
                                ]), 

                        ])->columnSpan(2),

                    // Right Column
                    Grid::make()
                        ->schema([
                            Section::make()
                                ->schema([
                                    Placeholder::make('display_qrcode')
                                        ->label(false)
                                        ->content(fn ($get) => 
                                            $get('series_no') 
                                            ? QrCode::size(200)->errorCorrection('H')->style('round')->generate($get('series_no'))
                                            : 'Generate Qrcode'
                                        )
                                        ->columnSpanFull(),

                                    Placeholder::make('display_code')
                                        ->label(false)
                                        ->content(fn ($get) => 
                                            $get('series_no') 
                                            ? $get('series_no')
                                            : ''
                                        )
                                        ->columnSpanFull(),
                                ])
                                ->footerActions([
                                    Action::make('generate_qrcode')
                                        ->label('Generate')
                                        ->submit('create')

                                ])                               

                        ])->columnSpan(1),
                ]),
        ]);
    }

}


