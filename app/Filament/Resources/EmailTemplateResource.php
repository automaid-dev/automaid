<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Admin-editable email content — subject + body for each of the app's
 * template-driven emails (see EmailTemplate::render(), used by
 * ApproveUserEmail and RegisterEmail). Deliberately no create/delete —
 * the app looks these up by a fixed set of keys, so only Edit makes
 * sense here; the full set is seeded by the
 * 2026_03_18_090000_create_email_templates_table migration.
 */
class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Email Templates';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Placeholder::make('key')
                            ->label('Template Key')
                            ->content(fn (?EmailTemplate $record) => $record?->key),
                        Placeholder::make('description')
                            ->label('When this is sent')
                            ->content(fn (?EmailTemplate $record) => $record?->description),
                        TextInput::make('subject')
                            ->label('Email Subject')
                            ->required()
                            ->maxLength(255),
                        RichEditor::make('body')
                            ->label('Email Body')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'blockquote', 'redo', 'undo',
                            ])
                            ->helperText('Use {{name}} anywhere you want the recipient\'s name inserted automatically.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(EmailTemplate::query())
            ->columns([
                TextColumn::make('label')
                    ->label('Email')
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Current subject')
                    ->limit(60),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make()->label('Edit'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
