<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'Edit Announcement'; // Custom title
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return 'Edit Announcement'; // Custom title        
    }

    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl();
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

                            Section::make() // First section
                                ->schema([
                                    TextInput::make('title')
                                        ->placeholder('e.g., New add-ons available on checkout')
                                        ->required(),
                                    RichEditor::make('description')
                                        ->placeholder('e.g., New add-ons are here! Customize your laundry with extra options like stain removal and premium softeners. Try them now!')
                                        ->toolbarButtons([
                                            'bold',
                                            'bulletList',
                                            'italic',
                                            'link',
                                            'orderedList',
                                        ])
                                ])

                        ])->columnSpan(2),

                    // Right Column
                    Grid::make()
                        ->schema([

                            Section::make() // First section
                                ->schema([
                                    Select::make('status')->options([
                                        'draft' => 'DRAFT',
                                        'published' => 'PUBLISHED',
                                    ])->required(),
                                    FileUpload::make('image_url')
                                        ->label('Banner Image')
                                        ->image()
                                        ->disk('s3')
                                        ->visibility('private')
                                        ->maxSize(10240)
                                        ->maxFiles(1)
                                        ->directory('automaid/announcements')
                                        ->helperText('File Types: JPG, PNG | Size: 1280 x 720 px'),
                                        
                                ])                             

                        ])->columnSpan(1),
                ]),
        ]);
    }

}
