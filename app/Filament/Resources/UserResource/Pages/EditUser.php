<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Pages\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * [getHeaderActions description]
     * @return [type] [description]
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record?->status === User::ONBOARDING)
                ->requiresConfirmation()
                ->modalHeading('Reject User')
                ->modalDescription('Are you sure you want to reject this user?')
                ->modalSubmitActionLabel('Reject')
                ->cancelParentActions()
                ->form([
                    Textarea::make('reason')->label('Rejection Reason')->required(),
                ])
                ->action(function (array $data) {

                    // uodate user
                    $this->record->update([
                        'status' => User::REJECTED,
                        'rejected_reason' => $data['reason'],
                        'updated_by' => auth()->user()->id,
                    ]);

                    $subject = 'Auto Maid: Your Registation Status: Rejected';
                    $emailContent = (new \App\Mail\RejectUserEmail($this->record->name, $subject, $data['reason']))->render();
                    (new \App\Services\OneSignalService())->sendEmail(
                        $this->record->email,
                        $subject,
                        $emailContent,
                    );
                    Notification::make()
                        ->title('User has been rejected.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * [getFormActions description]
     * @return [type] [description]
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return $this->record->name;
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return $this->record->name;        
    }

    /**
     * [getActions description]
     * @return [type] [description]
     */
    protected function getActions(): array
    {
        return [
            Impersonate::make()->record($this->getRecord()) // <--
        ];
    }

    /**
     * [getWidgets description]
     * @return [type] [description]
     */
    public function getWidgets(): array
    {
        return [
            UserResource\Widgets\CreateAddressListWidget::class,
            UserResource\Widgets\CreateBagListWidget::class,
            UserResource\Widgets\CreateOrderListWidget::class,
        ];
    }

    /**
     * [mutateFormDataBeforeSave description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {

        if (isset($data['rider'])) {
            $this->record->rider->bank_name = $data['rider']['bank_name'];
            $this->record->rider->bank_no = $data['rider']['bank_no'];

            $this->record->address_line_1 = $data['rider']['address_line_1'];
            $this->record->address_line_2 = $data['rider']['address_line_2'];

            if (isset($data['rider']['country_id'])) {
                $this->record->country_id = $data['rider']['country_id'];
            }
            if (isset($data['rider']['postcode'])) {
                $this->record->postcode = $data['rider']['postcode'];
            }
            if (isset($data['rider']['state_id'])) {
                $this->record->state_id = $data['rider']['state_id'];
            }
            if (isset($data['rider']['city'])) {
                $this->record->city = $data['rider']['city'];
            }

            $this->record->rider->emergency_name = $data['rider']['emergency_name'];
            $this->record->rider->emergency_phone = $data['rider']['emergency_phone'];
            $this->record->rider->emergency_relation = $data['rider']['emergency_relation'];
            $this->record->rider->type_rider = $data['rider']['type_rider'];
            $this->record->rider->type_vehicle = $data['rider']['type_vehicle'];
            $this->record->rider->plate_no = $data['rider']['plate_no'];
            $this->record->rider->vehicle_make = $data['rider']['vehicle_make'];
            $this->record->rider->vehicle_model = $data['rider']['vehicle_model'];
            $this->record->rider->vehicle_color = $data['rider']['vehicle_color'];
            $this->record->rider->vehicle_color_other = $data['rider']['vehicle_color_other'];

            $this->record->rider->ic_front = $data['rider']['ic_front'];
            $this->record->rider->ic_back = $data['rider']['ic_back'];
            $this->record->rider->license_front = $data['rider']['license_front'];
            $this->record->rider->license_back = $data['rider']['license_back'];
            $this->record->rider->jpj_grant = $data['rider']['jpj_grant'];

            $this->record->rider->status = $data['status'];
            $this->record->rider->rating = $data['rider']['rating'];
            $this->record->rider->updated_by = auth()->user()->id;

            $this->record->rider->save();
            unset($data['rider']);            
        }

        if (isset($data['merchant'])) {

            if (isset($data['merchant']['country_id'])) {
                $this->record->country_id = $data['merchant']['country_id'];
            }
            if (isset($data['merchant']['postcode'])) {
                $this->record->postcode = $data['merchant']['postcode'];
            }
            if (isset($data['merchant']['state_id'])) {
                $this->record->state_id = $data['merchant']['state_id'];
            }
            if (isset($data['merchant']['city'])) {
                $this->record->city = $data['merchant']['city'];
            }
            
            $this->record->merchant->type_merchant = $data['merchant']['type_merchant'];
            $this->record->merchant->bank_name = $data['merchant']['bank_name'];
            $this->record->merchant->bank_no = $data['merchant']['bank_no'];
            $this->record->merchant->unit_no = $data['merchant']['unit_no'];
            $this->record->merchant->block = $data['merchant']['block'];
            $this->record->merchant->address_line_1 = $data['merchant']['address_line_1'];
            $this->record->merchant->address_line_2 = $data['merchant']['address_line_2'];

            if (isset($data['merchant']['country_id'])) {
                $this->record->merchant->country_id = $data['merchant']['country_id'];
            }
            if (isset($data['merchant']['postcode'])) {
                $this->record->merchant->postcode = $data['merchant']['postcode'];
            }
            if (isset($data['merchant']['state_id'])) {
                $this->record->merchant->state_id = $data['merchant']['state_id'];
            }
            if (isset($data['merchant']['city'])) {
                $this->record->merchant->city = $data['merchant']['city'];
            }

            $this->record->merchant->company_name = $data['merchant']['company_name'];
            $this->record->merchant->ssm_no = $data['merchant']['ssm_no'];
            $this->record->merchant->washer_quantity = $data['merchant']['washer_quantity'];
            $this->record->merchant->dryer_quantity = $data['merchant']['dryer_quantity'];

            $this->record->merchant->ic_front = $data['merchant']['ic_front'];
            $this->record->merchant->ic_back = $data['merchant']['ic_back'];
            $this->record->merchant->ssm_cert = $data['merchant']['ssm_cert'];

            $this->record->merchant->business_option = $data['merchant']['business_option'];
            $this->record->merchant->service_categories = $data['merchant']['service_categories'];

            $this->record->merchant->status = $data['status'];
            $this->record->merchant->rating = $data['merchant']['rating'];
            $this->record->merchant->updated_by = auth()->user()->id;


            $this->record->merchant->save();
            unset($data['merchant']);
        }
        return $data;
    }

    /**
     * [mutateFormDataBeforeFill description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    public function mutateFormDataBeforeFill(array $data): array
    {
        if ($record = static::getRecord()) {
            $data['rider']['bank_name'] = $record->rider?->bank_name;
            $data['rider']['bank_no'] = $record->rider?->bank_no;

            $data['rider']['address_line_1'] = $record->address_line_1;
            $data['rider']['address_line_2'] = $record->address_line_2;
            $data['rider']['country_id'] = $record->country_id;
            $data['rider']['postcode'] = $record->postcode;
            $data['rider']['state_id'] = $record->state_id;
            $data['rider']['city'] = $record->city;
            
            $data['rider']['emergency_name'] = $record->rider?->emergency_name;
            $data['rider']['emergency_phone'] = $record->rider?->emergency_phone;
            $data['rider']['emergency_relation'] = $record->rider?->emergency_relation;            
            $data['rider']['type_rider'] = $record->rider?->type_rider;
            $data['rider']['type_vehicle'] = $record->rider?->type_vehicle;
            $data['rider']['plate_no'] = $record->rider?->plate_no;
            $data['rider']['vehicle_make'] = $record->rider?->vehicle_make;
            $data['rider']['vehicle_model'] = $record->rider?->vehicle_model;
            $data['rider']['vehicle_color'] = $record->rider?->vehicle_color;
            $data['rider']['vehicle_color_other'] = $record->rider?->vehicle_color_other;

            $data['rider']['ic_front'] = $record->rider?->ic_front;
            $data['rider']['ic_back'] = $record->rider?->ic_back;
            $data['rider']['license_front'] = $record->rider?->license_front;
            $data['rider']['license_back'] = $record->rider?->license_back;
            $data['rider']['jpj_grant'] = $record->rider?->jpj_grant;

            $data['rider']['rating'] = $record->rider?->rating;

            $data['merchant']['type_merchant'] = $record->merchant?->type_merchant;
            $data['merchant']['bank_name'] = $record->merchant?->bank_name;
            $data['merchant']['bank_no'] = $record->merchant?->bank_no;
            $data['merchant']['unit_no'] = $record->merchant?->unit_no;
            $data['merchant']['block'] = $record->merchant?->block;
            $data['merchant']['address_line_1'] = $record->merchant?->address_line_1;
            $data['merchant']['address_line_2'] = $record->merchant?->address_line_2;
            $data['merchant']['country_id'] = $record->merchant?->country_id;
            $data['merchant']['postcode'] = $record->merchant?->postcode;
            $data['merchant']['state_id'] = $record->merchant?->state_id;
            $data['merchant']['city'] = $record->merchant?->city;
            $data['merchant']['company_name'] = $record->merchant?->company_name;
            $data['merchant']['ssm_no'] = $record->merchant?->ssm_no;
            $data['merchant']['washer_quantity'] = $record->merchant?->washer_quantity;
            $data['merchant']['dryer_quantity'] = $record->merchant?->dryer_quantity;
            
            $data['merchant']['ic_front'] = $record->merchant?->ic_front;
            $data['merchant']['ic_back'] = $record->merchant?->ic_back;
            $data['merchant']['ssm_cert'] = $record->merchant?->ssm_cert;

            $data['merchant']['business_option'] = $record->merchant?->business_option;
            $data['merchant']['service_categories'] = $record->merchant?->service_categories;

            $data['merchant']['rating'] = $record->merchant?->rating;

            $data['is_active'] = $data['status'] === 'inactive' ? 0 : 1;
        }
        return $data;
    }

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
        return $form->schema([

            Tabs::make('Customer Tabs')
                ->columnSpanFull()
                ->contained(false)
                ->tabs([
                    Tabs\Tab::make('User Details')
                        ->schema([

                            Grid::make(3)
                                ->schema([

                                    // Left Column
                                    Grid::make()
                                        ->schema([
                                            Section::make('Customer Personal Information')
                                                ->schema([
                                                    Grid::make(2)
                                                        ->schema([
                                                            FileUpload::make('avatar')
                                                                ->label(false)
                                                                ->image()
                                                                ->avatar()
                                                                ->disk('s3')
                                                                ->directory('automaid/images/avatars')
                                                                ->visibility('public')
                                                                ->maxSize(200)
                                                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                                                                ->helperText('Allowed JPG, JPEG or PNG. Max size of 200K'),
                                                            TextInput::make('name')
                                                                ->label('Full Name')
                                                                ->required()
                                                                ->columnSpan(2),
                                                            TextInput::make('mobile_no')->required(),                                                            
                                                            TextInput::make('email')
                                                                ->email()
                                                                ->label('Email Address')
                                                                ->disabled()
                                                                ->required(),
                                                            DatePicker::make('dob')
                                                                ->label('Date of Birth')
                                                                ->native(false)
                                                                ->maxDate(now()),
                                                        ]),
                                                ]),

                                            Section::make('Password')
                                                ->schema([
                                                    Grid::make(2)
                                                        ->schema([
                                                            TextInput::make('password')
                                                                ->password()
                                                                ->maxLength(20)
                                                                ->revealable()
                                                                ->placeholder('Enter Password')                                
                                                                ->dehydrated(fn ($state) => filled($state))
                                                                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                                                ->required(fn(string $context): bool => $context === 'create'),
                                                            TextInput::make('password_confirmation')
                                                                ->required(fn(string $context): bool => $context === 'create')
                                                                ->password()
                                                                ->maxLength(20)
                                                                ->revealable()
                                                                ->placeholder('Enter Confirm Password')                                
                                                                ->same('password')
                                                                ->label('Confirm Password'),
                                                        ]),
                                                ]),

                                        ])->columnSpan(2),

                                    // Right Column
                                    Grid::make()
                                        ->schema([
                                            Section::make('Status')
                                                ->schema([
                                                    TextInput::make('role')
                                                        ->label('User Role')
                                                        ->afterStateHydrated(function ($component) {
                                                            $record = $component->getContainer()->getRecord();
                                                            $component->state($record?->getRoleNames()->first() ?? '');
                                                        })
                                                        ->disabled(),
                                                    Select::make('status')
                                                        ->label('Status')
                                                        ->options([
                                                            'active' => 'Active',
                                                            'inactive' => 'Inactive',
                                                            'pending' => 'Pending',
                                                        ])
                                                        ->placeholder('Select Status')
                                                        ->required(),
                                                ]),
                                        ])->columnSpan(1),

                                    \Filament\Forms\Components\Actions::make([
                                        \Filament\Forms\Components\Actions\Action::make('submit')
                                            ->label('Save Settings')
                                            ->submit('save')
                                            ->color('primary'),
                                    ])->columnSpanFull(),

                                ]),
                        ]),

                    Tabs\Tab::make('Address')
                        ->schema([                            
                            View::make('filament.resources.users.pages.edit.customer.address')
                        ]),
                    Tabs\Tab::make('Subscription')
                        ->schema([
                            View::make('filament.resources.users.pages.edit.customer.subscriptions')
                        ]),
                    Tabs\Tab::make('Bags')
                        ->schema([
                            View::make('filament.resources.users.pages.edit.customer.bags')
                        ]),
                    Tabs\Tab::make('Orders')
                        ->schema([
                            View::make('filament.resources.users.pages.edit.customer.orders')

                        ]),
                    Tabs\Tab::make('Transactions')
                        ->schema([
                            View::make('filament.resources.users.pages.edit.customer.transactions')

                        ]),
                ])->visible(fn ($livewire) => $livewire->record?->hasRole('customer')),











                Section::make('Admin Personal Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')->required(),                                                            
                                TextInput::make('role')
                                    ->label('User Role')
                                    ->afterStateHydrated(function ($component) {
                                        $record = $component->getContainer()->getRecord();
                                        $component->state($record?->getRoleNames()->first() ?? '');
                                    })
                                    ->disabled(),
                                Select::make('is_active')
                                    ->options([
                                        '1' => 'Active',
                                        '0' => 'Inactive',
                                    ])
                                    ->placeholder('Select Status')
                                    ->required(),                                                           
                                TextInput::make('email')->required(),                                                            
                                TextInput::make('mobile_no')->required(),                                                            

                            ]),
                    ])->visible(fn ($livewire) => $livewire->record?->hasRole('admin')),

                Section::make('Password')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('password')
                                    ->password()
                                    ->maxLength(20)
                                    ->revealable()
                                    ->placeholder('Enter Password')                                
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->required(fn(string $context): bool => $context === 'create'),
                                TextInput::make('password_confirmation')
                                    ->required(fn(string $context): bool => $context === 'create')
                                    ->password()
                                    ->maxLength(20)
                                    ->revealable()
                                    ->placeholder('Enter Confirm Password')                                
                                    ->same('password')
                                    ->label('Confirm Password'),
                            ]),
                    ])->visible(fn ($livewire) => $livewire->record?->hasRole('admin')),












                Tabs::make('Rider Tabs')
                    ->contained(false)
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Personal Information')
                            ->schema([

                                Grid::make(3)
                                    ->schema([

                                        // Left Column
                                        Grid::make()
                                            ->schema([

                                                Section::make('Rider Personal Information')
                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                FileUpload::make('avatar')
                                                                    ->label(false)
                                                                    ->disk('s3')
                                                                    ->directory('automaid/images/avatars')
                                                                    ->live()
                                                                    ->avatar()
                                                                    ->preserveFilenames(false),
                                                            ]),
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('name')
                                                                    ->columnSpanFull()
                                                                    ->required(),
                                                                TextInput::make('email')->email()->disabled()->required(),
                                                                TextInput::make('mobile_no')->required()->disabled(),
                                                                Select::make('id_type')
                                                                    ->label('ID Type')
                                                                    ->options([
                                                                        '1' => 'NRIC',
                                                                        '2' => 'Passport',
                                                                    ])
                                                                    ->placeholder('Select ID Type')
                                                                    ->required()
                                                                    
                                                                    ->reactive(),
                                                                TextInput::make('icno')
                                                                    ->label(fn ($get) => $get('id_type') == '2'
                                                                        ? 'Passport No'
                                                                        : 'Identity Card Number (NRIC)'
                                                                    )
                                                                    ->placeholder(fn ($get) => $get('id_type') == '2'
                                                                        ? 'Enter Passport Number'
                                                                        : 'Enter Identity Card Number (NRIC)'
                                                                    )
                                                                    ->required(),
                                                            ]),
                                                    ]),

                                                Section::make('Password')
                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('password')
                                                                    ->password()
                                                                    ->maxLength(20)
                                                                    ->revealable()
                                                                    ->placeholder('Enter Password')                                
                                                                    ->dehydrated(fn ($state) => filled($state))
                                                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                                                    ->required(fn(string $context): bool => $context === 'create'),
                                                                TextInput::make('password_confirmation')
                                                                    ->required(fn(string $context): bool => $context === 'create')
                                                                    ->password()
                                                                    ->maxLength(20)
                                                                    ->revealable()
                                                                    ->placeholder('Enter Confirm Password')                                
                                                                    ->same('password')
                                                                    ->label('Confirm Password'),
                                                            ]),
                                                    ]),
                                            ])->columnSpan(2),

                                        // Right Column
                                        Grid::make()
                                            ->schema([
                                                Section::make('Status')
                                                    ->schema([
                                                        TextInput::make('role')
                                                            ->label('User Role')
                                                            ->afterStateHydrated(function ($component) {
                                                                $record = $component->getContainer()->getRecord();
                                                                $component->state($record?->getRoleNames()->first() ?? '');
                                                            })
                                                            ->disabled(),
                                                        Select::make('rider.rating')
                                                            ->label('Rating')
                                                            ->options([
                                                                '5' => '5 Stars',
                                                                '4' => '4 Stars',
                                                                '3' => '3 Stars',
                                                                '2' => '2 Stars',
                                                                '1' => '1 Stars',
                                                                '0' => '0 Stars',
                                                            ])
                                                            ->placeholder('Select Rating'),                                                        
                                                        Select::make('status')
                                                            ->label('Status')
                                                            ->options([
                                                                'active' => 'Active',
                                                                'inactive' => 'Inactive',
                                                                'pending' => 'Pending',
                                                            ])
                                                            ->placeholder('Select Status')
                                                            ->required(),
                                                    ]),
                                            ])->columnSpan(1),
                                    ]),


                                    \Filament\Forms\Components\Actions::make([
                                        \Filament\Forms\Components\Actions\Action::make('submit')
                                            ->label('Save Settings')
                                            ->submit('save')
                                            ->color('primary'),
                                    ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Bank Info')
                            ->schema([
                                Section::make('Bank Info')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('rider.bank_name')
                                                    ->label('Bank Account Name')
                                                    ->relationship('rider.bank', 'name')
                                                    ->searchable()
                                                    ->preload(),                                                          
                                                TextInput::make('rider.bank_no')
                                                    ->label('Bank Account Number')
                                                    ->default(fn ($record) => $record->rider->bank_no),                                                    
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Home Address')
                            ->schema([
                                Section::make('Home Address')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('rider.address_line_1')->label('Address Line 1')->placeholder('e.g No. 123, Jalan PP22'),
                                                TextInput::make('rider.address_line_2')->label('Address Line 2 (if any)')->placeholder('e.g Taman Equine'),
                                                Select::make('rider.country_id')
                                                    ->label('Country')
                                                    ->relationship('country', 'name')
                                                    ->searchable()
                                                    ->placeholder('Select Country')
                                                    ->disabled(true)
                                                    ->preload(),
                                                TextInput::make('rider.postcode')->label('Postcode')->placeholder('e.g 43300')->disabled(true),
                                                Select::make('rider.state_id')
                                                    ->label('State')
                                                    ->relationship('state', 'name')
                                                    ->searchable()
                                                    ->placeholder('Select State')
                                                    ->disabled(true)
                                                    ->preload(),
                                                TextInput::make('rider.city')->label('City')->placeholder('e.g Seri Kembangan')->disabled(true),
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Active City')
                            ->schema([                            
                                View::make('filament.resources.users.pages.edit.city')
                            ]),

                        Tabs\Tab::make('Emergency Contact')
                            ->schema([
                                Section::make('Emergency Contact')
                                    ->schema([
                                        Grid::make(2) 
                                            ->schema([
                                                TextInput::make('rider.emergency_name')
                                                    ->label('Full Name')
                                                    ->placeholder('Enter Emergency Contact Full Name'),
                                                TextInput::make('rider.emergency_phone')
                                                    ->label('Phone')
                                                    ->placeholder('Enter Mobile No'),
                                                TextInput::make('rider.emergency_relation')
                                                    ->label('Relation')
                                                    ->placeholder('Enter Relation'),
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Vehicle Info')
                            ->schema([
                                Section::make('Vehicle Info')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('rider.type_rider')
                                                    ->label('Type of Rider')
                                                    ->options([
                                                        '1' => 'GIG Worker',
                                                        '2' => 'Staff From Auto Maid',
                                                    ])
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('rider.type_vehicle')
                                                    ->label('Vehicle Type')
                                                    ->options([
                                                        'motorcycle' => 'Motorcycle',
                                                        'van' => 'Van',
                                                        'car' => 'Car',
                                                        'mpv' => 'MPV',
                                                        'suv' => 'SUV',
                                                    ])
                                                    ->searchable()
                                                    ->preload(),
                                                TextInput::make('rider.plate_no')
                                                    ->label('Plate Number')
                                                    ->placeholder('e.g. NDP 9022'),
                                                TextInput::make('rider.vehicle_make')
                                                    ->label('Vehicle Make')
                                                    ->placeholder('e.g. Proton, Modenas'),
                                                TextInput::make('rider.vehicle_model')
                                                    ->label('Vehicle Model')
                                                    ->placeholder('e.g. HiAce, Kriss'),
                                                Select::make('rider.vehicle_color')
                                                    ->label('Vehicle Color')
                                                    ->options(
                                                        \App\Models\Color::orderBy('color', 'asc')->pluck('color', 'id') // Fetch categories from the DB
                                                    )
                                                    ->searchable()
                                                    ->preload(),
                                                TextInput::make('rider.vehicle_color_other')
                                                    ->label('Color (Other)')
                                                    ->placeholder('e.g. Turquoise'),
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Rider Verification')
                            ->schema([
                                Section::make('Rider Verification')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                FileUpload::make('rider.ic_front')
                                                    ->label('Identity Card (Front)')
                                                    ->disk('s3')
                                                    ->directory('automaid/images/riders')
                                                    ->live()
                                                    ->preserveFilenames(false)
                                                    ->helperText(function () {
                                                        if (!$this->record?->rider?->ic_front) {
                                                            return null;
                                                        }
                                                        $url = Storage::disk('s3')->url($this->record->rider->ic_front);
                                                        return new HtmlString(
                                                            '<a href="' . $url . '" target="_blank" class="text-blue-600 underline">Download</a>'
                                                        );
                                                    }),
                                                FileUpload::make('rider.ic_back')
                                                    ->label('Identity Card (Back)')
                                                    ->disk('s3')
                                                    ->directory('automaid/images/riders')
                                                    ->live()
                                                    ->preserveFilenames(false)
                                                    ->helperText(function () {
                                                        if (!$this->record?->rider?->ic_back) {
                                                            return null;
                                                        }
                                                        $url = Storage::disk('s3')->url($this->record->rider->ic_back);
                                                        return new HtmlString(
                                                            '<a href="' . $url . '" target="_blank" class="text-blue-600 underline">Download</a>'
                                                        );
                                                    }),
                                                FileUpload::make('rider.license_front')
                                                    ->label('Driving License (Front)')
                                                    ->disk('s3')
                                                    ->directory('automaid/images/riders')
                                                    ->live()
                                                    ->preserveFilenames(false)
                                                    ->helperText(function () {
                                                        if (!$this->record?->rider?->license_front) {
                                                            return null;
                                                        }
                                                        $url = Storage::disk('s3')->url($this->record->rider->license_front);
                                                        return new HtmlString(
                                                            '<a href="' . $url . '" target="_blank" class="text-blue-600 underline">Download</a>'
                                                        );
                                                    }),
                                                FileUpload::make('rider.license_back')
                                                    ->label('Driving License (Back)')
                                                    ->disk('s3')
                                                    ->directory('automaid/images/riders')
                                                    ->live()
                                                    ->preserveFilenames(false)
                                                    ->helperText(function () {
                                                        if (!$this->record?->rider?->license_back) {
                                                            return null;
                                                        }
                                                        $url = Storage::disk('s3')->url($this->record->rider->license_back);
                                                        return new HtmlString(
                                                            '<a href="' . $url . '" target="_blank" class="text-blue-600 underline">Download</a>'
                                                        );
                                                    }),
                                                FileUpload::make('rider.jpj_grant')
                                                    ->label('JPJ Grant')
                                                    ->disk('s3')
                                                    ->directory('automaid/images/riders')
                                                    ->live()
                                                    ->preserveFilenames(false)
                                                    ->helperText(function () {
                                                        if (!$this->record?->rider?->jpj_grant) {
                                                            return null;
                                                        }
                                                        $url = Storage::disk('s3')->url($this->record->rider->jpj_grant);
                                                        return new HtmlString(
                                                            '<a href="' . $url . '" target="_blank" class="text-blue-600 underline">Download</a>'
                                                        );
                                                    }),
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),
                        
                        Tabs\Tab::make('eWallet')
                            ->schema([
                                View::make('filament.resources.users.pages.edit.rider.ewallet')
                            ]),

                    ])->visible(fn ($livewire) => $livewire->record?->hasRole('rider')),











                Tabs::make('Merchant Tabs')
                    ->contained(false)
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Personal Information')
                            ->schema([

                                Grid::make(3)
                                    ->schema([

                                        // Left Column
                                        Grid::make()
                                            ->schema([

                                                Section::make('Merchant Personal Information')
                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                FileUpload::make('avatar')
                                                                    ->label(false)
                                                                    ->disk('s3')
                                                                    ->directory('automaid/images/avatars')
                                                                    ->live()
                                                                    ->avatar()
                                                                    ->preserveFilenames(false),
                                                            ]),
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('name')
                                                                    ->columnSpanFull()
                                                                    ->required(),
                                                                TextInput::make('email')->email()->disabled()->required(),
                                                                TextInput::make('mobile_no')->required()->disabled(),
                                                                Select::make('id_type')
                                                                    ->label('ID Type')
                                                                    ->options([
                                                                        '1' => 'NRIC',
                                                                        '2' => 'Passport',
                                                                    ])
                                                                    ->placeholder('Select ID Type')
                                                                    ->required()
                                                                    ->reactive(),
                                                                TextInput::make('icno')
                                                                    ->label(fn ($get) => $get('id_type') == '2'
                                                                        ? 'Passport No'
                                                                        : 'Identity Card Number (NRIC)'
                                                                    )
                                                                    ->placeholder(fn ($get) => $get('id_type') == '2'
                                                                        ? 'Enter Passport Number'
                                                                        : 'Enter Identity Card Number (NRIC)'
                                                                    )
                                                                    ->required(),
                                                            ]),
                                                    ]),

                                                Section::make('Password')
                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('password')
                                                                    ->password()
                                                                    ->maxLength(20)
                                                                    ->revealable()
                                                                    ->placeholder('Enter Password')                                
                                                                    ->dehydrated(fn ($state) => filled($state))
                                                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                                                    ->required(fn(string $context): bool => $context === 'create'),
                                                                TextInput::make('password_confirmation')
                                                                    ->required(fn(string $context): bool => $context === 'create')
                                                                    ->password()
                                                                    ->maxLength(20)
                                                                    ->revealable()
                                                                    ->placeholder('Enter Confirm Password')                                
                                                                    ->same('password')
                                                                    ->label('Confirm Password'),
                                                            ]),
                                                    ]),

                                            ])->columnSpan(2),

                                        // Right Column
                                        Grid::make()
                                            ->schema([
                                                Section::make('Status')
                                                    ->schema([
                                                        TextInput::make('role')
                                                            ->label('User Role')
                                                            ->afterStateHydrated(function ($component) {
                                                                $record = $component->getContainer()->getRecord();
                                                                $component->state($record?->getRoleNames()->first() ?? '');
                                                            })
                                                            ->disabled(),
                                                        Select::make('merchant.rating')
                                                            ->label('Rating')
                                                            ->options([
                                                                '5' => '5 Stars',
                                                                '4' => '4 Stars',
                                                                '3' => '3 Stars',
                                                                '2' => '2 Stars',
                                                                '1' => '1 Stars',
                                                                '0' => '0 Stars',
                                                            ])
                                                            ->placeholder('Select Rating'),  
                                                        Select::make('status')
                                                            ->label('Status')
                                                            ->options([
                                                                'active' => 'Active',
                                                                'inactive' => 'Inactive',
                                                                'pending' => 'Pending',
                                                            ])
                                                            ->required()
                                                            ->searchable()
                                                            ->preload(),
                                                    ]),
                                            ])->columnSpan(1),
                                    ]),
                                
                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),

                            ]),

                        Tabs\Tab::make('Bank Information')
                            ->schema([
                                Section::make('Bank Information')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('merchant.bank_name')
                                                    ->label('Bank Account Name')
                                                    ->relationship('merchant.bank', 'name')
                                                    ->searchable()
                                                    ->preload(),
                                                TextInput::make('merchant.bank_no')
                                                    ->label('Bank Account Number')
                                                    ->default(fn ($record) => $record->merchant->bank_no),
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Outlet Address')
                            ->schema([
                                Section::make('Outlet Address')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('merchant.unit_no')
                                                    ->label('Unit No')
                                                    ->default(fn ($record) => $record->merchant->unit_no)
                                                    ->placeholder('e.g. H-9-2'),
                                                TextInput::make('merchant.block')
                                                    ->label('Block')
                                                    ->placeholder('e.g. Level 1'),
                                                TextInput::make('merchant.address_line_1')->label('Address Line 1')->placeholder('e.g No. 123, Jalan PP22'),
                                                TextInput::make('merchant.address_line_2')->label('Address Line 2 (if any)')->placeholder('e.g Taman Equine'),
                                                Select::make('merchant.country_id')
                                                    ->label('Country')
                                                    ->relationship('country', 'name')
                                                    ->searchable()
                                                    ->placeholder('Select Country')
                                                    ->disabled(true)
                                                    ->preload(),
                                                TextInput::make('merchant.postcode')->label('Postcode')->placeholder('e.g 43300')->disabled(true),
                                                Select::make('merchant.state_id')
                                                    ->label('State')
                                                    ->relationship('state', 'name')
                                                    ->searchable()
                                                    ->placeholder('Select State')
                                                    ->disabled(true)
                                                    ->preload(),
                                                TextInput::make('merchant.city')->label('City')->placeholder('e.g Seri Kembangan')->disabled(true),
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Active City')
                            ->schema([                            
                                View::make('filament.resources.users.pages.edit.city')
                            ]),

                        Tabs\Tab::make('Company Information')
                            ->schema([
                                Section::make('Company Information')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('merchant.type_merchant')
                                                    ->label('Type of Merchant')
                                                    ->options([
                                                        '3' => 'Outlet Partner',
                                                        '4' => 'Auto Maid Outlet',
                                                    ])
                                                    ->required(),

                                                TextInput::make('merchant.company_name')
                                                    ->label('Company Name'),

                                                TextInput::make('merchant.ssm_no')
                                                    ->label('SSM Number'),

                                                Select::make('merchant.business_option')
                                                    ->label('Business Option')
                                                    ->options([
                                                        '1' => 'Corporate',
                                                        '2' => 'JV',
                                                        '3' => 'Franchise',
                                                    ])
                                                    ->required(),

                                                TextInput::make('merchant.washer_quantity')
                                                    ->label('Washer Quantity'),

                                                TextInput::make('merchant.dryer_quantity')
                                                    ->label('Dryer Quantity'),
                                            ]),

                                        CheckboxList::make('merchant.service_categories')
                                            ->label('Service Categories')
                                            ->options([
                                                'dry_cleaning' => 'Dry Cleaning',
                                                'shoe_cleaning' => 'Shoe Cleaning',
                                                'helmet_cleaning' => 'Helmet Cleaning',
                                                'wash_dry' => 'Wash & Dry',
                                            ])
                                            ->columns(2)
                                            ->required()
                                            ->rules(['array', 'min:1']),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),


                        Tabs\Tab::make('Merchant Verification')
                            ->schema([
                                Section::make('Merchant Verification')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                FileUpload::make('merchant.ic_front')
                                                    ->label('Identity Card (Front)')
                                                    ->disk('s3')
                                                    ->directory('automaid/images/merchants')
                                                    ->live()
                                                    ->preserveFilenames(false)
                                                    ->visibility('public')
                                                    ->helperText(function () {
                                                        if (!$this->record?->merchant?->ic_front) {
                                                            return null;
                                                        }
                                                        $url = Storage::disk('s3')->url($this->record->merchant->ic_front);
                                                        return new HtmlString(
                                                            '<a href="' . $url . '" target="_blank" class="text-blue-600 underline">Download</a>'
                                                        );
                                                    }),
                                                FileUpload::make('merchant.ic_back')
                                                    ->label('Identity Card (Back)')
                                                    ->disk('s3')
                                                    ->directory('automaid/images/merchants')
                                                    ->live()
                                                    ->preserveFilenames(false)
                                                    ->visibility('public')
                                                    ->helperText(function () {
                                                        if (!$this->record?->merchant?->ic_back) {
                                                            return null;
                                                        }
                                                        $url = Storage::disk('s3')->url($this->record->merchant->ic_back);
                                                        return new HtmlString(
                                                            '<a href="' . $url . '" target="_blank" class="text-blue-600 underline">Download</a>'
                                                        );
                                                    }),
                                                FileUpload::make('merchant.ssm_cert')
                                                    ->label('SSM Certificate')
                                                    ->disk('s3')
                                                    ->directory('automaid/images/merchants')
                                                    ->live()
                                                    ->preserveFilenames(false)
                                                    ->visibility('public')
                                                    ->helperText(function () {
                                                        if (!$this->record?->merchant?->ssm_cert) {
                                                            return null;
                                                        }
                                                        $url = Storage::disk('s3')->url($this->record->merchant->ssm_cert);
                                                        return new HtmlString(
                                                            '<a href="' . $url . '" target="_blank" class="text-blue-600 underline">Download</a>'
                                                        );
                                                    }),                                                    
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('submit')
                                        ->label('Save Settings')
                                        ->submit('save')
                                        ->color('primary'),
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('eWallet')
                            ->schema([
                                View::make('filament.resources.users.pages.edit.merchant.ewallet')
                            ]),

                    ])->visible(fn ($livewire) => $livewire->record?->hasRole('merchant')),





        ]);
    }

}
