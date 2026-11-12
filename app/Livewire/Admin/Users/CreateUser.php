<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Enums\UserAbility;
use App\Models\User;
use App\Notifications\Auth\WelcomeNewUser;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use App\Support\Auth\WelcomeLink;
use App\Support\Filament\GeneratePasswordAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Livewire\Component;

/**
 * Admin > Users > New user. The admin either emails the new user a link to
 * choose their own password (WelcomeNewUser, the default per
 * auth.welcome.email_by_default) or types one in. The link needs Fortify's
 * password reset feature, so without it only "Set a password now" is offered.
 */
class CreateUser extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    private const string SIGN_IN_EMAIL_LINK = 'email_link';

    private const string SIGN_IN_PASSWORD = 'password';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        Gate::authorize(UserAbility::CREATE, User::class);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.user_information'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label(__('admin.first_name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('last_name')
                            ->label(__('admin.last_name'))
                            ->required()
                            ->maxLength(255),

                        // Normalized before validation so the uniqueness check runs against
                        // the value User's mutator will actually store — otherwise a
                        // case-variant of an existing address passes here and then hits
                        // the DB unique index.
                        Forms\Components\TextInput::make('email')
                            ->label(__('admin.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->mutateStateForValidationUsing(fn (?string $state): ?string => User::normalizeEmail($state))
                            ->unique('users', 'email'),

                        Forms\Components\Radio::make('sign_in')
                            ->label(__('admin.new_user_sign_in'))
                            ->options([
                                self::SIGN_IN_EMAIL_LINK => __('admin.new_user_sign_in_email_link'),
                                self::SIGN_IN_PASSWORD => __('admin.new_user_sign_in_password'),
                            ])
                            ->descriptions([
                                self::SIGN_IN_EMAIL_LINK => __('admin.new_user_sign_in_email_link_hint', ['time' => WelcomeLink::lifetime()]),
                                self::SIGN_IN_PASSWORD => __('admin.new_user_sign_in_password_hint'),
                            ])
                            ->default(config()->boolean('auth.welcome.email_by_default') ? self::SIGN_IN_EMAIL_LINK : self::SIGN_IN_PASSWORD)
                            ->required()
                            ->live()
                            ->visible(fn (): bool => $this->mayEmailLink())
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->tap(GeneratePasswordAction::on(...))
                            ->required()
                            ->minLength(fn (): int => config()->integer('auth.password_min_length'))
                            ->rule(Password::default())
                            ->same('password_confirmation')
                            ->visible(fn (Get $get): bool => ! $this->sendsEmailLink($get('sign_in'))),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label(__('admin.confirm_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => ! $this->sendsEmailLink($get('sign_in'))),
                    ]),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        Gate::authorize(UserAbility::CREATE, User::class);

        $data = $this->form->getState();
        $sendsEmailLink = $this->sendsEmailLink($data['sign_in'] ?? null);

        $user = DB::transaction(function () use ($data, $sendsEmailLink): User {
            $user = new User;
            $user->forceFill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                // Nobody is told this one: they choose their own from the link.
                'password' => Hash::make($sendsEmailLink ? Str::password(40) : $data['password']),
                'active' => true,
                'email_verified_at' => now(),
            ])->save();

            if ($sendsEmailLink) {
                Audit::record('user.welcome_link_sent', $user, category: AuditCategory::ACCESS);
                $user->notify(new WelcomeNewUser);
            }

            return $user;
        });

        Notification::make()
            ->title(__('admin.user_created', ['name' => $user->name]))
            ->body($sendsEmailLink ? __('admin.user_created_link_sent', ['email' => $user->email]) : null)
            ->success()
            ->send();

        $this->redirect(route('users.show', $user));
    }

    /** The emailed link hands on to Fortify's reset page, so it needs that feature. */
    private function mayEmailLink(): bool
    {
        return Features::enabled(Features::resetPasswords());
    }

    private function sendsEmailLink(?string $signIn): bool
    {
        return $this->mayEmailLink() && $signIn === self::SIGN_IN_EMAIL_LINK;
    }

    public function render(): View
    {
        return view('livewire.admin.users.create-user');
    }
}
