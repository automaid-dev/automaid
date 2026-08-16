<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Services\OneSignalService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendTestEmail')
                ->label('Send Test Email')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->form([
                    TextInput::make('test_email')
                        ->label('Send test to')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->email),
                ])
                ->action(function (array $data) {
                    // Uses the form's current state — including any
                    // unsaved edits — so admin can preview a change
                    // before committing it, not just what's already
                    // saved to the database.
                    $state = $this->form->getState();
                    $subject = $state['subject'] ?? $this->record->subject;
                    $body = $state['body'] ?? $this->record->body;

                    $rendered = [
                        'subject' => str_replace('{{name}}', 'Test User', $subject),
                        'body' => str_replace('{{name}}', 'Test User', $body),
                    ];

                    try {
                        $response = (new OneSignalService())->sendEmail(
                            $data['test_email'],
                            '[TEST] ' . $rendered['subject'],
                            $rendered['body'],
                        );

                        // sendEmail() doesn't throw on a failed send —
                        // it just returns whatever OneSignal's API
                        // responded with, which can itself be an error
                        // payload. Check for that explicitly rather
                        // than assuming success just because nothing
                        // threw.
                        if (!empty($response['errors'])) {
                            Notification::make()
                                ->title('OneSignal rejected the test email')
                                ->body(is_string($response['errors']) ? $response['errors'] : json_encode($response['errors']))
                                ->danger()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Test email sent to ' . $data['test_email'])
                                ->success()
                                ->send();
                        }
                    } catch (\Throwable $th) {
                        Notification::make()
                            ->title('Could not send test email')
                            ->body($th->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\DeleteAction::make()->visible(false), // never delete a template the app depends on
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
