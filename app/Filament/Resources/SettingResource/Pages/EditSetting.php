<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    /**
     * [getHeaderActions description]
     * @return [type] [description]
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        return []; // Hide Breadcrumbs
    }

    /**
     * [getFormActions description]
     * @return [type] [description]
     */
    protected function getFormActions(): array
    {
        return [];
        
        // return [
        //     Action::make('cancel')
        //         ->label('Cancel')
        //         ->url($this->getResource()::getUrl()) // go back to list page
        //         ->color('gray'),

        //     Action::make('save')
        //         ->label('Save Changes')
        //         ->submit('save') // triggers form save
        //         ->color('primary'),
        // ];
    }

    public function getTitle(): string
    {
        return 'General Settings'; // Custom title
    }

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([

                Tabs::make('Setting Tabs')
                    ->activeTab(fn ($get, $record) => 1)
                    ->persistTabInQueryString('tab')
                    ->contained(false)
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Commission & Fee')
                            ->schema([
                                Grid::make(1)
                                    ->schema([

                                        Section::make('Fees') // First section
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('wash_fee')
                                                            ->label('Wash Fee (RM)')
                                                            ->placeholder('e.g., 20')
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2))            
                                                            ->required()
                                                            ->numeric()
                                                            ->helperText('per Bag'),
                                                        TextInput::make('delivery_price')
                                                            ->label('Delivery Fee — 1st Bag (RM)')
                                                            ->placeholder('e.g., 10')
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2))
                                                            ->required()
                                                            ->numeric()
                                                            ->helperText('Charged for the 1st bag in every order'),
                                                        \Filament\Forms\Components\Select::make('delivery_additional_bag_type')
                                                            ->label('2nd Bag Onward — Charge As')
                                                            ->options([
                                                                'flat' => 'Flat amount (RM)',
                                                                'percent' => 'Percentage of 1st bag price',
                                                            ])
                                                            ->default('flat')
                                                            ->live()
                                                            ->helperText('How each additional bag beyond the 1st is priced'),
                                                        TextInput::make('delivery_additional_bag_value')
                                                            ->label(fn (\Filament\Forms\Get $get) => $get('delivery_additional_bag_type') === 'percent'
                                                                ? '2nd Bag Onward — Percentage (%)'
                                                                : '2nd Bag Onward — Amount (RM)')
                                                            ->placeholder(fn (\Filament\Forms\Get $get) => $get('delivery_additional_bag_type') === 'percent' ? 'e.g., 50' : 'e.g., 5')
                                                            ->numeric()
                                                            ->helperText(fn (\Filament\Forms\Get $get) => $get('delivery_additional_bag_type') === 'percent'
                                                                ? 'e.g. 50 means each additional bag is charged 50% of the 1st bag delivery price'
                                                                : 'Charged per additional bag — e.g. RM5 means the 2nd, 3rd, etc. bags are each RM5, regardless of the 1st bag price. Leave blank to charge every bag the same as the 1st bag (old behavior).'),
                                                        TextInput::make('bag_price')
                                                            ->label('Laundry Bag Price (RM)')
                                                            ->placeholder('e.g., 10')
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2))         
                                                            ->required()
                                                            ->numeric()
                                                            ->helperText('per Unit'),
                                                        TextInput::make('birthday_reward_amount')
                                                            ->label('Birthday Reward Amount (RM)')
                                                            ->placeholder('e.g., 10')
                                                            ->numeric()
                                                            ->helperText('per User'),
                                                        TextInput::make('birthday_reward_min')
                                                            ->placeholder('e.g., 50')
                                                            ->label('Birthday Reward Minimum Spend')
                                                            ->numeric()
                                                            ->helperText('per User'),
                                                        TextInput::make('insurance_fee')
                                                            ->label('Insurance Fee (RM)')
                                                            ->placeholder('e.g., 2')
                                                            ->required()
                                                            ->helperText('per Order'),
                                                        TextInput::make('insurance_coverage')
                                                            ->label('Insurance Coverage Amount (RM)')
                                                            ->placeholder('e.g., 2')
                                                            ->numeric()
                                                            ->helperText('per Order'),
                                                        TextInput::make('sst_percent')
                                                            ->label('SST (%)')
                                                            ->placeholder('e.g., 8')
                                                            ->numeric()
                                                            ->helperText('Applied to washing + delivery charges on every booking'),
                                                        TimePicker::make('same_day_cutoff_time')
                                                            ->label('Same-Day Delivery Cutoff Time')
                                                            ->seconds(false)
                                                            ->helperText('Bookings for today must start before this time'),
                                                    ]),
                                            ]),

                                        Section::make('Subscription Fees/Discounts') // Second section
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('subscription_bronze_price')
                                                            ->label('Bronze Plan Price (RM)')
                                                            ->placeholder('e.g., 50.00')
                                                            ->numeric()
                                                            ->helperText('per Month')
                                                            ->formatStateUsing(fn ($state) =>
                                                                filled($state) ? number_format((float) $state, 2) : null
                                                            )
                                                            ->dehydrateStateUsing(fn ($state) =>
                                                                filled($state) ? str_replace(',', '', $state) : null
                                                            ),
                                                        TextInput::make('subscription_bronze_orders')
                                                            ->label('Bronze Plan Orders Included')
                                                            ->placeholder('e.g., 4')
                                                            ->numeric()
                                                            ->helperText('per Month, e.g. 4 orders'),
                                                        TextInput::make('subscription_silver_price')
                                                            ->label('Silver Plan Price (RM)')
                                                            ->placeholder('e.g., 70.00')
                                                            ->numeric()
                                                            ->helperText('per Month')
                                                            ->formatStateUsing(fn ($state) =>
                                                                filled($state) ? number_format((float) $state, 2) : null
                                                            )
                                                            ->dehydrateStateUsing(fn ($state) =>
                                                                filled($state) ? str_replace(',', '', $state) : null
                                                            ),
                                                        TextInput::make('subscription_silver_orders')
                                                            ->label('Silver Plan Orders Included')
                                                            ->placeholder('e.g., 6')
                                                            ->numeric()
                                                            ->helperText('per Month, e.g. 6 orders'),
                                                        TextInput::make('subscription_platinum_price')
                                                            ->label('Platinum Plan Price (RM)')
                                                            ->placeholder('e.g., 99.00')
                                                            ->numeric()
                                                            ->helperText('per Month — unlimited orders')
                                                            ->formatStateUsing(fn ($state) =>
                                                                filled($state) ? number_format((float) $state, 2) : null
                                                            )
                                                            ->dehydrateStateUsing(fn ($state) =>
                                                                filled($state) ? str_replace(',', '', $state) : null
                                                            ),
                                                        TextInput::make('subscription_price')
                                                            ->label('Legacy Subscription Price (RM)')
                                                            ->placeholder('e.g., 100.00')
                                                            ->numeric()
                                                            ->helperText('Deprecated — kept only for subscriptions created before plans existed')
                                                            ->formatStateUsing(fn ($state) =>
                                                                filled($state) ? number_format((float) $state, 2) : null
                                                            )
                                                            ->dehydrateStateUsing(fn ($state) =>
                                                                filled($state) ? str_replace(',', '', $state) : null
                                                            ),
                                                        TextInput::make('total_bag_free_wash')
                                                            ->label('No. of Bag for Free Wash')
                                                            ->required()
                                                            ->numeric()
                                                            ->helperText('per Bag'),
                                                        TextInput::make('total_bag_free_delivery')
                                                            ->label('No. of Bag for Free Delivery (currently unused)')
                                                            ->required()
                                                            ->numeric()
                                                            ->helperText('Delivery is now always charged in full for every bag, including a subscriber\'s free-wash bag — this setting no longer has any effect. Kept only so existing saved values aren\'t lost; safe to ignore.'),
                                                        TextInput::make('discount_percent')
                                                            ->label('Discount Percentage for Add-ons (%)')
                                                            ->placeholder('e.g., 2')
                                                            ->required()
                                                            ->numeric()
                                                            ->helperText('per Order'),
                                                        TextInput::make('discount_limit')
                                                            ->label('Discount Limit per Order (RM)')
                                                            ->placeholder('e.g., 2')
                                                            ->required()
                                                            ->numeric()
                                                            ->helperText('per Order'),
                                                    ]),
                                            ]),

                                    ]),
                                
                                Grid::make(4)
                                    ->schema([
                                        Grid::make()
                                            ->schema([
                                                Section::make('Commission Merchant')
                                                    ->schema([
                                                        TextInput::make('merchant_commission')
                                                            ->label('Commission Limit (RM)')
                                                            ->placeholder('e.g., 100')
                                                            ->numeric()
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2)),
                                                        TextInput::make('merchant_outlet_partner_commission')
                                                            ->label('Outlet Partner Commission Rate (%)')
                                                            ->placeholder('e.g., 15')
                                                            ->numeric()
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2)),                                            
                                                        TextInput::make('merchant_automaid_outlet_commission')
                                                            ->label('Auto Maid Outlet Commission Rate (%)')
                                                            ->placeholder('e.g., 10')
                                                            ->numeric()
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2)),
                                                        TextInput::make('merchant_minimum_commission')
                                                            ->label('Minimum Commission (RM)')
                                                            ->placeholder('e.g., 10')
                                                            ->numeric()
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2))                                            
                                                            ->helperText('This applies if the order amount is RM0'),                                                        
                                                    ]),
                                            ])->columnSpan(2),

                                        Grid::make()
                                            ->schema([
                                                Section::make('Commission Rider')
                                                    ->schema([
                                                        TextInput::make('rider_commission')
                                                            ->label('Commission Limit (RM)')
                                                            ->placeholder('e.g., 50')
                                                            ->numeric()
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2)),
                                                        TextInput::make('rider_gig_worker_commission')
                                                            ->label('Gig Worker Commission Rate (%)')
                                                            ->placeholder('e.g., 15')
                                                            ->numeric()
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2)),                                            
                                                        TextInput::make('rider_staff_automaid_commission')
                                                            ->label('Staff from Auto Maid Commission Rate (%)')
                                                            ->placeholder('e.g., 10')
                                                            ->numeric()
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2)),
                                                        TextInput::make('rider_minimum_commission')
                                                            ->label('Minimum Commission Rate (RM)')
                                                            ->placeholder('e.g., 10')
                                                            ->numeric()
                                                            ->formatStateUsing(fn (string $state): string => number_format($state, 2))                                            
                                                            ->helperText('This applies if the order amount is RM0'),                                                                                       
                                                    ]),
                                            ])->columnSpan(2),

                                        FormActions::make([
                                            FormActions\Action::make('submit')
                                                ->label('Update')
                                                ->submit('save')
                                                ->color('primary'),
                                        ])->columnSpanFull()->alignEnd(),


                                    ]),
                            ]),

                        Tabs\Tab::make('Add-Ons')
                            ->schema([
                                View::make('filament.resources.setting.addon')
                            ]),

                        Tabs\Tab::make('Vouchers & Discount')
                            ->schema([
                                View::make('filament.resources.setting.voucher')
                            ]),


                        Tabs\Tab::make('Support & Communication')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Section::make('Customer Service Details') // First section
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('admin_email')
                                                            ->label('Admin Email')
                                                            ->placeholder('e.g., support@automaid.asia')
                                                            ->required(),
                                                        TextInput::make('phone_no')
                                                            ->label('Phone Number')
                                                            ->placeholder('e.g., 03 3456 7890')
                                                            ->required(),
                                                        TextInput::make('whatapp_no')
                                                            ->label('WhatApp Number')
                                                            ->placeholder('e.g., 019 567 7788')
                                                            ->required(),
                                                    ]),
                                            ]),

                                        Section::make('Legal Documents')
                                            ->schema([
                                                Placeholder::make('terms_conditions_manual_upload')
                                                    ->label('Terms & Conditions (PDF)')
                                                    ->helperText('Shown to customers in the app as an acceptance step before booking payment.')
                                                    ->content(fn () => new \Illuminate\Support\HtmlString(
                                                        '<div style="display:flex;flex-direction:column;gap:8px;">' .
                                                        ($this->record->terms_conditions_url
                                                            ? '<div>Current file: <a href="' . e($this->record->terms_conditions_url) . '" target="_blank" style="color:#2563eb;text-decoration:underline;">view current PDF</a></div>'
                                                            : '<div style="color:#6b7280;">No file uploaded yet.</div>')
                                                        . '<form action="' . route('admin.settings.upload-terms') . '" method="POST" enctype="multipart/form-data" style="display:flex;align-items:center;gap:8px;">'
                                                        . csrf_field()
                                                        . '<input type="file" name="terms_conditions" accept="application/pdf" required style="border:1px solid #d1d5db;border-radius:6px;padding:6px;">'
                                                        . '<button type="submit" style="background:#f59e0b;color:#fff;padding:6px 16px;border-radius:6px;border:none;cursor:pointer;">Upload</button>'
                                                        . '</form>'
                                                        . '</div>'
                                                    )),
                                            ]),

                                        Section::make('Company Information')
                                            ->description('Shown as a letterhead on customer-facing receipts.')
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('company_name')
                                                            ->label('Company Name')
                                                            ->placeholder('e.g., Automaid Sdn Bhd')
                                                            ->columnSpanFull(),
                                                        Textarea::make('company_address')
                                                            ->label('Company Address')
                                                            ->placeholder("e.g., 12 Jalan Automaid, 50000 Kuala Lumpur, Malaysia")
                                                            ->rows(3)
                                                            ->columnSpanFull(),
                                                        TextInput::make('company_phone')
                                                            ->label('Company Phone')
                                                            ->placeholder('e.g., 03 3456 7890'),
                                                        TextInput::make('company_email')
                                                            ->label('Company Email')
                                                            ->placeholder('e.g., hello@automaid.asia'),
                                                        TextInput::make('company_registration_no')
                                                            ->label('Company Registration No.')
                                                            ->placeholder('e.g., 202601012345 (optional)'),
                                                    ]),
                                            ]),

                                    ]),

                                    Actions::make([
                                        Action::make('update_support')
                                            ->label('Update')
                                            ->submit('save'), // triggers the form save
                                    ])
                                    ->alignment(Alignment::End),
                            ]),




                    ]),
            ]);
    }





}
