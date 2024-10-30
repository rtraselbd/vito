<?php

namespace App\Web\Pages\Settings\Profile\Widgets;

use App\Helpers\Agent;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BrowserSessionsForm extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static bool $isLazy = false;

    protected static string $view = 'components.form';

    public function getFormSchema(): array
    {
        return [
            Section::make('Browser Sessions')
                ->description('Manage and log out your active sessions on other browsers and devices.')
                ->schema([
                    ViewField::make('browserSessions')
                        ->label('Browser Sessions')
                        ->hiddenLabel()
                        ->view('fields.browser-sessions')
                        ->viewData(['data' => $this->getSessions()]),
                    Actions::make([
                        Actions\Action::make('deleteBrowserSessions')
                            ->label('Log Out Other Browser Sessions')
                            ->requiresConfirmation()
                            ->modalHeading('Log Out Other Browser Sessions')
                            ->modalDescription('Please enter your password to confirm you would like to log out of your other browser sessions across all of your devices.')
                            ->modalSubmitActionLabel('Log Out Other Browser Sessions')
                            ->form([
                                TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->label('Password')
                                    ->required(),
                            ])
                            ->action(function (array $data) {
                                $this->logoutOtherBrowserSessions($data['password']);
                            })
                            ->modalWidth('2xl'),
                    ]),

                ]),
        ];
    }

    private function getSessions(): array
    {
        if (config(key: 'session.driver') !== 'database') {
            return [];
        }

        return collect(
            value: DB::connection(config(key: 'session.connection'))->table(table: config(key: 'session.table', default: 'sessions'))
                ->where(column: 'user_id', operator: Auth::user()->getAuthIdentifier())
                ->latest(column: 'last_activity')
                ->get()
        )->map(callback: function ($session): object {
            $agent = $this->createAgent($session);

            return (object) [
                'device' => [
                    'browser' => $agent->browser(),
                    'desktop' => $agent->isDesktop(),
                    'mobile' => $agent->isMobile(),
                    'tablet' => $agent->isTablet(),
                    'platform' => $agent->platform(),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === request()->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        })->toArray();
    }

    private function createAgent(mixed $session)
    {
        return tap(
            value: new Agent,
            callback: fn ($agent) => $agent->setUserAgent(userAgent: $session->user_agent)
        );
    }

    private function logoutOtherBrowserSessions($password): void
    {
        if (! Hash::check($password, Auth::user()->password)) {
            Notification::make()
                ->danger()
                ->title('The password you entered was incorrect. Please try again.')
                ->send();

            return;
        }

        Auth::guard()->logoutOtherDevices($password);

        request()->session()->put([
            'password_hash_'.Auth::getDefaultDriver() => Auth::user()->getAuthPassword(),
        ]);

        $this->deleteOtherSessionRecords();

        Notification::make()
            ->success()
            ->title('All other browser sessions have been logged out successfully.')
            ->send();
    }

    private static function deleteOtherSessionRecords()
    {
        if (config(key: 'session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', Auth::user()->getAuthIdentifier())
            ->where('id', '!=', request()->session()->getId())
            ->delete();
    }
}
