<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class CreateUser extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        Gate::authorize('create', User::class);

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

                        Forms\Components\TextInput::make('email')
                            ->label(__('admin.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email'),

                        Forms\Components\TextInput::make('password')
                            ->label(__('admin.password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->same('password_confirmation'),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label(__('admin.confirm_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        Gate::authorize('create', User::class);

        $data = $this->form->getState();

        $user = new User;
        $user->forceFill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'active' => true,
            'email_verified_at' => now(),
        ])->save();

        Notification::make()
            ->title(__('admin.user_created', ['name' => $user->name]))
            ->success()
            ->send();

        $this->redirect(route('users.show', $user));
    }

    public function render(): View
    {
        return view('livewire.admin.users.create-user');
    }
}
