<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel_subscription')
                ->label('Cancel Subscription')
                ->requiresConfirmation()
                ->modalHeading('Confirmation')
                ->modalDescription('Are you sure you want to cancel this subcription?')
                ->modalSubmitActionLabel('Confirm')
                ->color('danger')
                ->visible(fn ($record) => $record->status !== 'cancelled')
                ->action(function ($record) {
                    try {
                        $user = $record->user;

                        // Cancel bag subscription
                        if ($record->bag) {
                            $record->bag->status = \App\Models\Bag::CANCELLED;
                            $record->bag->updated_by = auth()->id();
                            $record->bag->save();
                        }

                        // Cancel payment recurring
                        if ($record->recurring_active) {
                            $record->recurring_active->status = \App\Models\PaymentRecurring::CANCELLED;
                            $record->recurring_active->updated_by = auth()->id();
                            $record->recurring_active->save();
                        }

                        // Insert unsubscribe
                        $unsubscribe = \App\Models\Unsubscribe::firstOrCreate(
                            [
                                'user_id' => $user->id,
                                'subscription_id' => $record->id,
                                'order_id' => $record->order_id,
                            ],
                            [
                                'amount' => $record->order->grand_total,
                                'status' => 'unsubscribe',
                            ]
                        );

                        // Insert activity
                        \App\Models\Activity::firstOrCreate(
                            [
                                'order_id' => $record->order_id,
                                'user_id' => $user->id,
                                'user_type' => 'customer',
                                'title' => 'Cancel Subscription',
                                'status' => \App\Models\Activity::ACTIVE,
                            ]
                        );

                        // Update subscription status
                        $record->status = \App\Models\Subscription::CANCELLED;
                        $record->updated_by = $user->id;
                        $record->save();

                        // Send cancel email
                        $subject = 'Auto Maid: Your subscription has been cancelled';
                        $emailContent = (new \App\Mail\CancelSubscriptionEmail($user->name, $subject))->render();
                        (new \App\Services\OneSignalService())->sendEmail(
                            $user->email,
                            $subject,
                            $emailContent,
                        );
                        Notification::make()
                            ->title('Subscription cancelled successfully.')
                            ->success()
                            ->send();
                    } catch (\Throwable $th) {
                        Notification::make()
                            ->title('Failed to cancel subscription')
                            ->body($th->getMessage())
                            ->danger()
                            ->send();
                    }
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

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return $this->record->user->name . ' (#' . $this->record->id . ')';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return $this->record->user->name . ' (#' . $this->record->id . ')';        
    }

    /**
     * [mutateFormDataBeforeSave description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {   
        if ($data['status'] || $data['created_at']) {
            $this->record->status = $data['status'];
            $this->record->created_at = $data['created_at'];
            $this->record->save();
        }

        if ($data['next_payment_date']) {
            $this->record->recurring_latest->next_payment_date = $data['next_payment_date'];
            $this->record->recurring_latest->save();
            unset($data['next_payment_date']); 
        }
        return $data;
    }

    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl(); // This goes back to the list page
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
                            Section::make('Billing Information')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_billing_name')
                                                ->disableLabel(true)
                                                ->content('Name'),
                                            Placeholder::make('billing_name')
                                                ->disableLabel(true)
                                                ->content($this->record->user->name)
                                                ->extraAttributes(['class' => 'text-right']),

                                            Placeholder::make('label_billing_email')
                                                ->disableLabel(true)
                                                ->content('Email Address'),
                                            Placeholder::make('billing_email')
                                                ->disableLabel(true)
                                                ->content($this->record->user->email)
                                                ->extraAttributes(['class' => 'text-right']),

                                            Placeholder::make('label_billing_phone')
                                                ->disableLabel(true)
                                                ->content('Phone'),
                                            Placeholder::make('billing_phone')
                                                ->disableLabel(true)
                                                ->content($this->record->user->mobile_no)
                                                ->extraAttributes(['class' => 'text-right']),
                                        ]),
                                ]),

                            Section::make('Address Details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_billing_address')
                                                ->disableLabel(true)
                                                ->content('Billing Address'),
                                            Placeholder::make('billing_address')
                                                ->disableLabel(true)
                                                ->content(fn ($record) => $this->getBillingAddress($record))
                                                ->extraAttributes(['class' => 'text-right']),

                                            View::make('filament.placeholders.delivery-address-label'),
                                            Placeholder::make('delivery_address')
                                                ->disableLabel(true)
                                                ->content(fn ($record) => $this->getDeliveryAddress($record))
                                                ->extraAttributes(['class' => 'text-right']),
                                    ]),
                                ]),
                        ])->columnSpan(2),

                    // Right Column
                    Grid::make()
                        ->schema([
                            Section::make('Subscription Details')
                                ->schema([
                                    Grid::make(3)
                                        ->schema([
                                            Placeholder::make('details_status_label')->label(false)->content('Status'),
                                            View::make('filament.placeholders.status-badge')->columnSpan(2),
                                            // Placeholder::make('details_status')
                                            //     ->label(false)
                                            //     ->content(fn () => ucfirst($this->record->status))
                                            //     ->extraAttributes(['class' => 'text-right'])
                                            //     ->columnSpan(2),

                                            Placeholder::make('details_id_label')->label(false)->content('ID'),
                                            Placeholder::make('details_id')
                                                ->label(false)
                                                ->content('#' . $this->record->id)
                                                ->extraAttributes(['class' => 'text-right'])
                                                ->columnSpan(2),

                                            Placeholder::make('details_plan_label')->label(false)->content('Plan'),
                                            Placeholder::make('details_plan')
                                                ->label(false)
                                                ->content('Auto Maid Subscription')
                                                ->extraAttributes(['class' => 'text-right'])
                                                ->columnSpan(2),

                                            Placeholder::make('details_amount_label')->label(false)->content('Amount'),
                                            Placeholder::make('details_amount')
                                                ->label(false)
                                                ->content('RM' . $this->record->payment->amount . ' / month')
                                                ->extraAttributes(['class' => 'text-right'])
                                                ->columnSpan(2),

                                            Placeholder::make('details_created_label')->label(false)->content('Created At'),
                                            Placeholder::make('details_created')
                                                ->label(false)
                                                ->content($this->record->start_date)
                                                ->extraAttributes(['class' => 'text-right'])
                                                ->columnSpan(2),

                                            Placeholder::make('details_renew_label')->label(false)->content('Renews At'),
                                            Placeholder::make('details_renew')
                                                ->label(false)
                                                ->content($this->record->end_date)
                                                ->extraAttributes(['class' => 'text-right'])
                                                ->columnSpan(2),
                                        ]),
                                ]),

                            FormActions::make([
                                    FormActions\Action::make('cancel')
                                        ->label('Back')
                                        ->url($this->getResource()::getUrl())
                                        ->color('gray'),
                                ])->columnSpanFull()->alignEnd(),
                        ])->columnSpan(1),
                ]),
        ]);
    }

    // Address Function
    protected function getBillingAddress($record): string
    {
        return implode(', ', array_filter([
            $record->order->billing_address_line_1 ?? null,
            $record->order->billing_address_line_2 ?? null,
            $record->order->billing_postcode ?? null,
            $record->order->billing_city ?? null,
            $record->order->billing_state ?? null,
            $record->order->billing_country ?? null,
        ], fn ($val) => filled($val)));
    }

    protected function getDeliveryAddress($record): string
    {
        return implode(', ', array_filter([
            $record->order->delivery_address_line_1 ?? null,
            $record->order->delivery_address_line_2 ?? null,
            $record->order->delivery_postcode ?? null,
            $record->order->delivery_city ?? null,
            $record->order->delivery_state ?? null,
            $record->order->delivery_country ?? null,
        ], fn ($val) => filled($val)));
    }
}
