<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Outlet;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Pages\Actions\Action;
use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Address;
use Filament\Forms\Components\CheckboxList;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
    
    protected static bool $canCreateAnother = false;

    /**
     * [mutateFormDataBeforeCreate description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // set customer address
        if ($data['role'] == 'customer') {
            $data['address']['address_title'] = $data['address_title'] ?? null;
            $data['address']['unit_no'] = $data['unit_no'] ?? null;
            $data['address']['floor'] = $data['floor'] ?? null;
            $data['address']['block'] = $data['block'] ?? null;
            $data['address']['address_line_1'] = $data['address_line_1'] ?? null;
            $data['address']['address_line_2'] = $data['address_line_2'] ?? null;
            $data['address']['country_id'] = $data['country_id'] ?? null;
            $data['address']['postcode'] = $data['postcode'] ?? null;
            $data['address']['state_id'] = $data['state_id'] ?? null;
            $data['address']['city'] = $data['city'] ?? null;
            $data['address']['status'] = Address::ACTIVE;
        }

        // set rider info
        if ($data['role'] == 'rider') {
            $data['rider']['type_rider'] = $data['type_rider'] ?? null;
            $data['rider']['type_vehicle'] = $data['type_vehicle'] ?? null;
            $data['rider']['emergency_name'] = $data['emergency_name'] ?? null;
            $data['rider']['emergency_phone'] = $data['emergency_phone'] ?? null;
            $data['rider']['emergency_relation'] = $data['emergency_relation'] ?? null;
            $data['rider']['country_code_emergency'] = $data['country_code_emergency'] ?? null;
            $data['rider']['plate_no'] = $data['plate_no'] ?? null;
            $data['rider']['vehicle_make'] = $data['vehicle_make'] ?? null;
            $data['rider']['vehicle_model'] = $data['vehicle_model'] ?? null;
            $data['rider']['vehicle_color'] = $data['vehicle_color'] ?? null;
            $data['rider']['vehicle_color_other'] = $data['vehicle_color_other'] ?? null;
            $data['rider']['status'] = 'active';

            $data['rider']['ic_front'] = $data['ic_front'] ?? null;
            $data['rider']['ic_back'] = $data['ic_back'] ?? null;
            $data['rider']['license_front'] = $data['license_front'] ?? null;
            $data['rider']['license_back'] = $data['license_back'] ?? null;
            $data['rider']['jpj_grant'] = $data['jpj_grant'] ?? null;
            
            $data['rider']['bank_name'] = $data['bank_name'] ?? null;
            $data['rider']['bank_no'] = $data['bank_no'] ?? null;
        }

        // set merchant info
        if ($data['role'] == 'merchant') {

            // insert outlet
            $outlet = Outlet::create([
                'name' => $data['company_name'] ?? null,
                'slug' => Str::slug($data['company_name']),
                'unit_no' => $data['unit_no'] ?? null,
                'block' => $data['block'] ?? null,
                'address_line_1' => $data['address_line_1'] ?? null,
                'address_line_2' => $data['address_line_2'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'city' => $data['city'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'country_id' => $data['country_id'] ?? null,
                'status' => 'active',
            ]);

            // insert merchant
            $data['merchant']['outlet_id'] = $outlet->id;
            $data['merchant']['type_merchant'] = $data['type_merchant'] ?? null;
            $data['merchant']['washer_quantity'] = $data['washer_quantity'] ?? null;
            $data['merchant']['dryer_quantity'] = $data['dryer_quantity'] ?? null;
            $data['merchant']['status'] = 'active';

            $data['merchant']['ssm_no'] = $data['ssm_no'] ?? null;
            $data['merchant']['company_name'] = $data['company_name'] ?? null;
            $data['merchant']['bank_name'] = $data['bank_name'] ?? null;
            $data['merchant']['bank_no'] = $data['bank_no'] ?? null;
            $data['merchant']['business_option'] = $data['business_option'] ?? null;
            $data['merchant']['service_categories'] = $data['service_categories'] ?? [];

            $data['merchant']['unit_no'] = $data['unit_no'] ?? null;
            $data['merchant']['block'] = $data['block'] ?? null;
            $data['merchant']['address_line_1'] = $data['address_line_1'] ?? null;
            $data['merchant']['address_line_2'] = $data['address_line_2'] ?? null;
            $data['merchant']['postcode'] = $data['postcode'] ?? null;
            $data['merchant']['city'] = $data['city'] ?? null;
            $data['merchant']['state_id'] = $data['state_id'] ?? null;
            $data['merchant']['country_id'] = $data['country_id'] ?? null;

            $data['merchant']['ic_front'] = $data['ic_front'] ?? null;
            $data['merchant']['ic_back'] = $data['ic_back'] ?? null;
            $data['merchant']['ssm_cert'] = $data['ssm_cert'] ?? null;
        }

        $data['user_id'] = auth()->id();
        $data['email_verified_at'] = now();
        $data['status'] = 'active';
        return $data;
    }

    /**
     * [handleRecordCreation description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = static::getModel()::create($data);
        $user->assignRole($data['role']);

        $addressData = $data['address'] ?? [];
        unset($data['address']);
        if ($addressData) {
            $user->addresses()->create($addressData);
        }

        $riderData = $data['rider'] ?? [];
        unset($data['rider']);
        if ($riderData) {
            $user->rider()->create($riderData);
        }

        $merchantData = $data['merchant'] ?? [];
        unset($data['merchant']);
        if ($merchantData) {
            $user->merchant()->create($merchantData);
        }
        return $user;
    }


    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
        return $form->schema([

            Section::make('User Role')
                ->schema([
                    Radio::make('role')
                        ->label('')
                        ->options([
                            'admin' => 'Admin',
                            'customer' => 'Customer',
                            'rider' => 'Rider',
                            'merchant' => 'Merchant',
                        ])
                        ->required()
                        ->reactive()
                        ->inline()
                ]),






            Section::make('Admin Personal Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->placeholder('Enter Full Name')
                                ->required(),
                            Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                ])
                                ->default('active')
                                ->placeholder('Select Status')
                                ->required(),
                            Grid::make(2) 
                                ->schema([
                                    TextInput::make('email')
                                        ->label('Email Address')
                                        ->required()
                                        ->placeholder('Enter Email Address')
                                        ->email()
                                        ->unique(table: 'users', column: 'email')
                                        ->columnSpan(1),
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('country_code_mobile')
                                                ->label('Country Code')
                                                ->default('60')
                                                ->required(),
                                            TextInput::make('mobile_no')
                                                ->label('Mobile No')
                                                ->placeholder('Enter Mobile No')
                                                ->required(),
                                        ])
                                        ->columnSpan(1),
                                ]),                            
                        ]),
                ])->visible(fn ($get) => $get('role') === 'admin'),
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
                ])->visible(fn ($get) => $get('role') === 'admin'),










            Section::make('Customer Personal Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->placeholder('Enter Full Name')
                                ->required(),
                            Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                ])
                                ->default('active')                                
                                ->placeholder('Select Status')
                                ->required(),
                            Grid::make(2) 
                                ->schema([
                                    TextInput::make('email')
                                        ->label('Email Address')
                                        ->required()
                                        ->email()
                                        ->placeholder('Enter Email Address')
                                        ->unique(table: 'users', column: 'email')                                        
                                        ->columnSpan(1),
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('country_code_mobile')
                                                ->label('Country Code')
                                                ->placeholder('Enter Country Code')
                                                ->required(),
                                            TextInput::make('mobile_no')
                                                ->label('Mobile No')
                                                ->placeholder('Enter Mobile No')                                                
                                                ->required(),
                                        ])
                                        ->columnSpan(1),
                                ]),    
                        ]),
                ])->visible(fn ($get) => $get('role') === 'customer'),
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
                ])->visible(fn ($get) => $get('role') === 'customer'),

                Section::make('Rider Personal Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Full Name')
                                    ->placeholder('Enter Full Name')
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->placeholder('Select Status')
                                    ->required(),
                                Grid::make(2) 
                                    ->schema([
                                        TextInput::make('email')
                                            ->label('Email Address')
                                            ->required()
                                            ->placeholder('Enter Email Address')
                                            ->email()
                                            ->unique(table: 'users', column: 'email')
                                            ->columnSpan(1),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('country_code_mobile')
                                                    ->label('Country Code')
                                                    ->placeholder('Enter Country Code')
                                                    ->required(),
                                                TextInput::make('mobile_no')
                                                    ->label('Mobile No')
                                                    ->placeholder('Enter Mobile No,')
                                                    ->required(),
                                            ])
                                            ->columnSpan(1),
                                    ]),    
                                Select::make('id_type')
                                    ->label('ID Type')
                                    ->options([
                                        '1' => 'NRIC',
                                        '2' => 'Passport',
                                    ])
                                    ->default('1')
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
                    ])->visible(fn ($get) => $get('role') === 'rider'),

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
                    ])->visible(fn ($get) => $get('role') === 'rider'),
                    
                Section::make('Bank Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('bank_name')
                                    ->label('Bank Account Name')
                                    ->options(
                                        \App\Models\Bank::orderBy('name', 'asc')->pluck('name', 'id') // Fetch categories from the DB
                                    )
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('bank_no')
                                    ->label('Bank Account Number')
                                    ->placeholder('e.g. 7234435435'),
                            ]),
                    ])->visible(fn ($get) => $get('role') === 'rider'),

                Section::make('Emergency Contact')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Grid::make(2) 
                                    ->schema([
                                        TextInput::make('emergency_name')
                                            ->label('Full Name')
                                            ->placeholder('Enter Emergency Contact Full Name')
                                            ->columnSpan(1),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('country_code_emergency')
                                                    ->label('Country Code')
                                                    ->placeholder('Enter Country Code')
                                                    ->default('60'),
                                                TextInput::make('emergency_phone')
                                                    ->label('Phone')
                                                    ->placeholder('Enter Mobile No'),
                                            ])
                                            ->columnSpan(1),
                                        ]),
                                TextInput::make('emergency_relation')
                                    ->label('Relation')
                                    ->placeholder('e.g. 019345455'),
                            ]),
                    ])->visible(fn ($get) => $get('role') === 'rider'),

                Section::make('Rider & Vehicle Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('type_rider')
                                    ->label('Type of Rider')
                                    ->options([
                                        '1' => 'GIG Worker',
                                        '2' => 'Staff From Auto Maid',
                                    ])
                                    ->placeholder('Select Type of Rider'),
                                Select::make('type_vehicle')
                                    ->label('Vehicle Type')
                                    ->options([
                                        'motorcycle' => 'Motorcycle',
                                        'van' => 'Van',
                                        'car' => 'Car',
                                        'mpv' => 'MPV',
                                        'suv' => 'SUV',
                                    ])
                                    ->placeholder('Select Vehicle Type'),
                                TextInput::make('plate_no')
                                    ->label('Plate Number')
                                    ->placeholder('e.g. NDP 9022'),
                                TextInput::make('vehicle_make')
                                    ->label('Vehicle Make')
                                    ->placeholder('e.g. Proton, Modenas'),
                                TextInput::make('vehicle_model')
                                    ->label('Vehicle Model')
                                    ->placeholder('e.g. HiAce, Kriss'),
                                Select::make('vehicle_color')
                                    ->label('Vehicle Color')
                                    ->options(
                                        \App\Models\Color::orderBy('color', 'asc')->pluck('color', 'id') // Fetch categories from the DB
                                    )
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('vehicle_color_other')
                                    ->label('Color (Other)')
                                    ->placeholder('e.g. Turquoise'),
                            ]),
                    ])->visible(fn ($get) => $get('role') === 'rider'),

                Section::make('Rider Verification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('ic_front')
                                    ->label('Identity Card (Front)')
                                    ->disk('public')
                                    ->directory('automaid/images/riders')
                                    ->live()
                                    ->preserveFilenames(false),
                                FileUpload::make('ic_back')
                                    ->label('Identity Card (Back)')
                                    ->disk('public')
                                    ->directory('automaid/images/riders')
                                    ->live()
                                    ->preserveFilenames(false),
                                FileUpload::make('license_front')
                                    ->label('Driving License (Front)')
                                    ->disk('public')
                                    ->directory('automaid/images/riders')
                                    ->live()
                                    ->preserveFilenames(false),
                                FileUpload::make('license_back')
                                    ->label('Driving License (Back)')
                                    ->disk('public')
                                    ->directory('automaid/images/riders')
                                    ->live()
                                    ->preserveFilenames(false),
                                FileUpload::make('jpj_grant')
                                    ->label('JPJ Grant')
                                    ->disk('public')
                                    ->directory('automaid/images/riders')
                                    ->live()
                                    ->preserveFilenames(false),
                            ]),
                    ])->visible(fn ($get) => $get('role') === 'rider'),








                Section::make('Merchant Personal Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Full Name')
                                    ->placeholder('Enter Full Name')
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->placeholder('Select Status')
                                    ->required(),
                                Grid::make(2) 
                                    ->schema([
                                        TextInput::make('email')
                                            ->label('Email Address')
                                            ->required()
                                            ->placeholder('Enter Email Address')
                                            ->email()
                                            ->unique(table: 'users', column: 'email')
                                            ->columnSpan(1),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('country_code_mobile')
                                                    ->label('Country Code')
                                                    ->placeholder('Enter Country Code')
                                                    ->required(),
                                                TextInput::make('mobile_no')
                                                    ->label('Mobile No')
                                                    ->placeholder('Enter Mobile No')
                                                    ->required(),
                                            ])
                                            ->columnSpan(1),
                                    ]),
                                Select::make('id_type')
                                    ->label('ID Type')
                                    ->options([
                                        '1' => 'NRIC',
                                        '2' => 'Passport',
                                    ])
                                    ->default('1')
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
                    ])->visible(fn ($get) => $get('role') === 'merchant'),
                    
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
                    ])->visible(fn ($get) => $get('role') === 'merchant'),

                Section::make('Bank Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('bank_name')
                                    ->label('Bank Account Name')
                                    ->options(
                                        \App\Models\Bank::orderBy('name', 'asc')->pluck('name', 'id') // Fetch categories from the DB
                                    )
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('bank_no')
                                    ->label('Bank Account Number')
                                    ->placeholder('e.g. 7234435435'),
                            ]),
                    ])->visible(fn ($get) => $get('role') === 'merchant'),

                Section::make('Merchant & Company Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('type_merchant')
                                    ->label('Type of Merchant')
                                    ->options([
                                        '3' => 'Outlet Partner',
                                        '4' => 'Auto Maid Outlet',
                                    ])
                                    ->placeholder('Select Type of Merchant')
                                    ->required(),

                                TextInput::make('company_name')
                                    ->label('Company Name')
                                    ->placeholder('e.g. Dobi Hana'),

                                TextInput::make('ssm_no')
                                    ->label('SSM Number')
                                    ->placeholder('e.g. H-293123'),

                                Select::make('business_option')
                                    ->label('Business Option')
                                    ->options([
                                        '1' => 'Corporate',
                                        '2' => 'JV',
                                        '3' => 'Franchise',
                                    ])
                                    ->placeholder('Select Business Option')
                                    ->required(),

                                TextInput::make('washer_quantity')
                                    ->label('Washer Quantity')
                                    ->placeholder('e.g. 10'),

                                TextInput::make('dryer_quantity')
                                    ->label('Dryer Quantity')
                                    ->placeholder('e.g. 15'),
                            ]),

                        CheckboxList::make('service_categories')
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
                    ])
                    ->visible(fn ($get) => $get('role') === 'merchant'),

                Section::make('Merchant Verification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('ic_front')
                                    ->label('Identity Card (Front)')
                                    ->disk('public')
                                    ->directory('automaid/images/merchants')
                                    ->live()
                                    ->preserveFilenames(false),
                                FileUpload::make('ic_back')
                                    ->label('Identity Card (Back)')
                                    ->disk('public')
                                    ->directory('automaid/images/merchants')
                                    ->live()
                                    ->preserveFilenames(false),
                                FileUpload::make('ssm_cert')
                                    ->label('SSM Certificate')
                                    ->disk('public')
                                    ->directory('automaid/images/merchants')
                                    ->live()
                                    ->preserveFilenames(false),
                            ]),
                    ])->visible(fn ($get) => $get('role') === 'merchant'),






        ]);
    }

}
