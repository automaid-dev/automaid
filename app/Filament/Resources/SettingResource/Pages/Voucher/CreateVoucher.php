<?php

namespace App\Filament\Resources\SettingResource\Pages\Voucher;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Voucher;

class CreateVoucher extends CreateRecord
{
    protected static string $resource = SettingResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'New Vouchers & Discount';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.resources.settings.edit', ['record' => 1]) . '?tab=-commission-fee-tab' => 'General Settings',
            'New Vouchers & Discount',
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * [handleRecordCreation description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function handleRecordCreation(array $data): Voucher
    {
        return Voucher::create($this->prepareVoucherData($data));
    }

    /**
     * The three usage-limit checkboxes (enable_usage_limit,
     * enable_usage_limit_per_customer, enable_max_discount_amount_cap)
     * are form-only toggles, not real Voucher columns — this strips
     * them out and, for any left unchecked, nulls the corresponding
     * real value column so a stale number from a previously-checked
     * box can never silently linger as an enforced limit.
     *
     * @param  array $data
     * @return array
     */
    protected function prepareVoucherData(array $data): array
    {
        foreach ([
            'enable_usage_limit' => 'usage_limit',
            'enable_usage_limit_per_customer' => 'usage_limit_per_customer',
            'enable_max_discount_amount_cap' => 'max_discount_amount_cap',
        ] as $checkboxKey => $valueKey) {
            if (empty($data[$checkboxKey])) {
                $data[$valueKey] = null;
            }
            unset($data[$checkboxKey]);
        }
        return $data;
    }

    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    protected function getRedirectUrl(): string
    {
        return route('filament.admin.resources.settings.edit', ['record' => 1]) . '?tab=-vouchers-discount-tab';
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
                                            TextInput::make('code')
                                                ->label('Voucher Code')
                                                ->columnSpan(2)
                                                ->placeholder('e.g., FreeShippingRM5'),
                                            Textarea::make('description')
                                                ->label('Description')
                                                ->columnSpan(2)
                                                ->rows(8)
                                                ->autosize()
                                                ->placeholder('e.g., Waive shipping fee'),
                                        ]),
                                ]),
                        ])->columnSpan(2),
                
                    // Right Column
                    Grid::make()
                        ->schema([
                            Section::make()
                                ->schema([
                                    Select::make('discount_type')
                                        ->label('Discount Type')
                                        ->placeholder('Select discount type')
                                        ->options([
                                            '1' => 'RM',
                                            '2' => '%',
                                        ]),
                                    TextInput::make('discount_amount')
                                        ->label('Discount amount')
                                        ->numeric()
                                        ->placeholder('e.g., 20'),
                                    Select::make('status')
                                        ->label('Status')
                                        ->placeholder('Select status')
                                        ->default('active')
                                        ->options([
                                            'active' => 'Active',
                                            'inactive' => 'Inactive',
                                        ]),
                                ]),

                            FormActions::make([
                                FormActions\Action::make('cancel')
                                    ->label('Back')
                                    ->extraAttributes(['x-on:click' => 'history.back()'])
                                    ->color('gray'),
                                FormActions\Action::make('submit')
                                    ->label('Create')
                                    ->submit('save')
                                    ->color('primary'),
                            ])->columnSpanFull()->alignEnd(),
                    ])->columnSpan(1),
                ]),

            // Minimum purchase requirements — exactly one of these
            // three applies at a time (radio, not checkboxes).
            Section::make('Minimum Purchase Requirements')
                ->schema([
                    Radio::make('minimum_requirement_type')
                        ->label(false)
                        ->options([
                            Voucher::MIN_REQUIREMENT_NONE => 'No minimum requirements',
                            Voucher::MIN_REQUIREMENT_AMOUNT => 'Minimum purchase amount',
                            Voucher::MIN_REQUIREMENT_ITEMS => 'Minimum total items in order',
                        ])
                        ->default(Voucher::MIN_REQUIREMENT_NONE)
                        ->live(),
                    TextInput::make('minimum_purchase_amount')
                        ->label('Minimum amount')
                        ->prefix('RM')
                        ->numeric()
                        ->placeholder('e.g., 50')
                        ->visible(fn ($get) => $get('minimum_requirement_type') === Voucher::MIN_REQUIREMENT_AMOUNT),
                    TextInput::make('minimum_total_items')
                        ->label('Minimum total items (bags/pcs)')
                        ->numeric()
                        ->placeholder('e.g., 3')
                        ->visible(fn ($get) => $get('minimum_requirement_type') === Voucher::MIN_REQUIREMENT_ITEMS),
                ]),

            // Maximum usage limit — all three are independent
            // checkboxes; admin can enable none, some, or all.
            Section::make('Maximum Usage Limit')
                ->schema([
                    Checkbox::make('enable_usage_limit')
                        ->label('Limit the number of times this discount can be used in total')
                        ->live(),
                    TextInput::make('usage_limit')
                        ->label('Total usage limit')
                        ->numeric()
                        ->placeholder('e.g., 100')
                        ->visible(fn ($get) => $get('enable_usage_limit')),
                    Checkbox::make('enable_usage_limit_per_customer')
                        ->label('Limit the number of times this discount can be used per customer')
                        ->live(),
                    TextInput::make('usage_limit_per_customer')
                        ->label('Usage limit per customer')
                        ->numeric()
                        ->placeholder('e.g., 1')
                        ->visible(fn ($get) => $get('enable_usage_limit_per_customer')),
                    Checkbox::make('enable_max_discount_amount_cap')
                        ->label('Limit the total discount amount (capped)')
                        ->live(),
                    TextInput::make('max_discount_amount_cap')
                        ->label('Total discount amount cap')
                        ->prefix('RM')
                        ->numeric()
                        ->placeholder('e.g., 5000')
                        ->visible(fn ($get) => $get('enable_max_discount_amount_cap')),
                ]),

            // Period of usage
            Section::make('Period of Usage')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            DatePicker::make('start_at')
                                ->label('Start Date')
                                ->native(false),
                            DatePicker::make('expired_at')
                                ->label('End Date')
                                ->native(false)
                                ->afterOrEqual('start_at'),
                        ]),
                ]),

        ]);
    }
}
