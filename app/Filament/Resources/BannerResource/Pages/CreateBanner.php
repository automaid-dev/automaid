<?php

namespace App\Filament\Resources\BannerResource\Pages;

use App\Filament\Resources\BannerResource;
use App\Models\Banner;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;

class CreateBanner extends CreateRecord
{
    protected static string $resource = BannerResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * [mutateFormDataBeforeCreate description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'New Banner';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return 'New Banner';
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
                                    Textarea::make('description')
                                        ->placeholder('e.g., Save your money and time with unlimited laundry service.')
                                        ->helperText('Only shown on the onboarding carousel — dashboard banners just use the title above.')
                                        ->rows(3)
                                        ->maxLength(1000),
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
                                            Banner::TARGET_CUSTOMER => 'Customer app — dashboard',
                                            Banner::TARGET_MERCHANTRIDER => 'Merchant/Rider app — dashboard',
                                            Banner::TARGET_ONBOARDING => 'Customer app — onboarding (before login)',
                                        ])
                                        ->required()
                                        ->default(Banner::TARGET_CUSTOMER),
                                    Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true),
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
