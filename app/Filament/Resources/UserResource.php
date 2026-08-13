<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Filament\Resources\UserResource\Widgets\UsersStats;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = 'Management';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
        // return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->rowIndex()
                    ->label('No') 
                    ->sortable(), 
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.display_name')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(strtolower($state)))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'pending' => 'danger',
                        'onboarding' => 'info',
                        'rejected' => 'gray',
                    })
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'ACTIVE',
                        'inactive' => 'INACTIVE',
                        'pending' => 'PENDING',
                        'onboarding' => 'ONBOARDING',
                        'rejected' => 'REJECTED',
                    ])
                    ->placeholder('All Status')
                    ->label(false),
                SelectFilter::make('role')
                    ->options(fn () => Role::pluck('display_name', 'name')->toArray()) // Fetch roles dynamically
                    ->query(function ($query, $data) {
                        if ($data['value']) {
                            $name = $data['value'];
                            $query->whereHas('roles', function ($q) use ($name) {
                                $q->where('name', $name);
                            });
                        }
                    })
                    ->placeholder('All Roles')
                    ->label(false),
                SelectFilter::make('type')
                    ->options([
                        '1' => 'GIG WORKER',
                        '2' => 'STAFF FROM AUTO MAID',
                        '3' => 'PARTNER OUTLET',
                        '4' => 'AUTO MAID OUTLET',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if (in_array($value, ['1', '2'])) {
                            $query->whereHas('rider', function ($q) use ($value) {
                                $q->where('type_rider', $value);
                            });
                        } elseif (in_array($value, ['3', '4'])) {
                            $query->whereHas('merchant', function ($q) use ($value) {
                                $q->where('type_merchant', $value);
                            });
                        }
                    })
                    ->placeholder('All Types')
                    ->label(false),
                SelectFilter::make('city_id')
                    ->label(false)
                    ->options(function () {
                        return \App\Models\CityUser::query()
                            ->where('is_active', 1)
                            ->whereHas('user.roles', function ($q) {
                                $q->whereIn('name', ['rider', 'merchant']);
                            })
                            ->with('city')
                            ->get()
                            ->pluck('city.name', 'city_id') // SHOW NAME
                            ->unique()
                            ->sort()
                            ->toArray();
                    })
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if ($value) {
                            $query->whereHas('covered_locations', function ($q) use ($value) {
                                $q->where('city_id', $value)
                                  ->where('is_active', 1);
                            });
                        }
                    })
                    ->placeholder('All Covered Cities'),
                SelectFilter::make('duty_status')
                    ->label(false)
                    ->options([
                        'rider_on'        => 'ON DUTY (RIDER)',
                        'rider_off'       => 'OFF DUTY (RIDER)',
                        'merchant_open'   => 'OPEN (MERCHANT)',
                        'merchant_closed' => 'CLOSED (MERCHANT)',
                    ])
                    ->query(function ($query, array $data) {

                        $value = $data['value'] ?? null;

                        if (!$value) {
                            return;
                        }

                        match ($value) {
                            'rider_on' => $query
                                ->whereHas('roles', fn ($q) => $q->where('name', 'rider'))
                                ->where('is_duty', 1),

                            'rider_off' => $query
                                ->whereHas('roles', fn ($q) => $q->where('name', 'rider'))
                                ->where('is_duty', 0),

                            'merchant_open' => $query
                                ->whereHas('roles', fn ($q) => $q->where('name', 'merchant'))
                                ->where('is_duty', 1),

                            'merchant_closed' => $query
                                ->whereHas('roles', fn ($q) => $q->where('name', 'merchant'))
                                ->where('is_duty', 0),
                        };
                    })
                    ->placeholder('All Duty Status'),
                    
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Action::make('approve')
                    ->label('')
                    ->tooltip('Approve User')
                    ->icon('heroicon-m-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Approve User')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'active',
                            'updated_by' => auth()->user()->id,
                        ]);
                        $rendered = \App\Models\EmailTemplate::render(
                            \App\Models\EmailTemplate::USER_APPROVED,
                            ['name' => $record->name]
                        );
                        $emailContent = (new \App\Mail\ApproveUserEmail($record->name, $rendered['subject'], $rendered['body']))->render();
                        (new \App\Services\OneSignalService())->notifyUser(
                            $record,
                            \App\Models\CustomerNotification::ACCOUNT_APPROVED,
                            $rendered['subject'],
                            'Your application has been approved — you can start now.',
                            $emailContent
                        );
                    })
                    ->visible(fn ($record) => $record->status === 'onboarding'),
                Impersonate::make()
                    ->label('')->icon('heroicon-m-key')
                    ->visible(function ($record) {
                        return $record->hasRole(['super_admin', 'admin']) && $record->id !== auth()->id();
                    }),
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->headerActions([
                Action::make('searchHelp')
                    ->label('')
                    ->icon('heroicon-o-question-mark-circle')
                    ->iconButton() // renders as a small icon button only
                    ->modalHeading('User Guide')
                    ->modalContent(fn() => view('filament.tables.user-status-guide'))
                    ->modalWidth('4xl')
                    ->form([])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
            ])

            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'transaction' => Pages\ViewTransaction::route('/{record}/transaction/{transaction}'),
        ];
    }

    // public static function getWidgets(): array
    // {
    //     return [
    //         UsersStats::class,
    //     ];
    // }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('roles', function ($q) {
            return $q->where('name', '!=', 'super_admin');
        });
    }

    // public static function getNavigationBadge(): ?string
    // {
    //     return parent::getEloquentQuery()->whereHas('roles', function ($q) {
    //         return $q->where('name', '!=', 'super_admin');
    //     })->count();
    // }
    

    
    
}
