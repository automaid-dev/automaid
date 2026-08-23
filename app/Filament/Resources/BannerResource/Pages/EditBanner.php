<?php

namespace App\Filament\Resources\BannerResource\Pages;

use App\Filament\Resources\BannerResource;
use App\Models\Banner;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditBanner extends EditRecord
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * [mutateFormDataBeforeSave description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'Edit Banner';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return 'Edit Banner';
    }

    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    protected function getRedirectUrl(): string
    {
        return BannerResource::getUrl();
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

                    // Left column
                    Grid::make()
                        ->schema([

                            Section::make()
                                ->schema([
                                    TextInput::make('title')
                                        ->placeholder('e.g., New Promotion!!')
                                        ->helperText('Shown as a caption under the banner in the app.')
                                        ->maxLength(255),
                                    TextInput::make('link')
                                        ->label('Link')
                                        ->url()
                                        ->placeholder('https://laundrybar.com.my/laundry-pickup/')
                                        ->helperText('Optional — where tapping the banner should open.'),
                                ]),

                        ])->columnSpan(2),

                    // Right column
                    Grid::make()
                        ->schema([

                            Section::make()
                                ->schema([
                                    Select::make('target')
                                        ->label('Show on')
                                        ->options([
                                            Banner::TARGET_CUSTOMER => 'Customer app',
                                            Banner::TARGET_MERCHANTRIDER => 'Merchant/Rider app',
                                        ])
                                        ->required(),
                                    Toggle::make('is_active')
                                        ->label('Active'),
                                    FileUpload::make('image_path')
                                        ->label('Banner Image')
                                        ->image()
                                        ->disk('s3')
                                        ->visibility('public')
                                        ->maxSize(10240)
                                        ->maxFiles(1)
                                        ->storeFiles()
                                        ->directory('automaid/banners')
                                        ->required()
                                        ->helperText('File should not exceed 10mb. Recommended ratio is 1:1.'),
                                ]),

                        ])->columnSpan(1),
                ]),
        ]);
    }
}
