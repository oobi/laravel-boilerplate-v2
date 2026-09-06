<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\Role;
use App\Models\User;
use App\Support\Panels\Registry\PanelRegistry;
use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class EditUser extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public User $user;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(User $user): void
    {
        $this->user = $user;

        Gate::authorize('update', $this->user);

        $this->form->fill([
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'active' => $this->user->active,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $components = PanelRegistry::formSections('users.edit', $this->user, Auth::user())
            ->flatMap(fn ($section): array => $section->components($this->user))
            ->all();

        return $schema
            ->components($components)
            ->statePath('data');
    }

    public function save(): void
    {
        Gate::authorize('update', $this->user);

        // Disabled fields (e.g. a self-edit's active toggle) are excluded from
        // getState() by Filament, so this trusts whatever the registered panels expose.
        $data = $this->form->getState();

        // Sensitive fields are re-checked at the write boundary against their own
        // ability, independent of whatever the form schema disables/excludes —
        // a generic "update" grant must never silently authorize a status change.
        if (array_key_exists('active', $data) && $data['active'] !== $this->user->active) {
            Gate::authorize('toggleActive', $this->user);
        }

        $this->user->update($data);

        Notification::make()
            ->title(__('admin.user_updated'))
            ->success()
            ->send();

        $this->redirect(route('users.show', $this->user));
    }

    /**
     * Deliberately separate from save()'s generic form state — role
     * assignment is its own ability (assignRole), never implied by a
     * "manage users" grant. See UserPolicy::assignRole() docblock.
     */
    public function manageRolesAction(): Action
    {
        return Action::make('manageRoles')
            ->label(__('admin.manage_roles'))
            ->icon('heroicon-o-shield-check')
            ->visible(fn (): bool => Gate::allows('assignRole', $this->user))
            ->fillForm(fn (): array => ['roles' => $this->user->roles->pluck('name')->all()])
            ->schema([
                Forms\Components\CheckboxList::make('roles')
                    ->label(__('admin.roles'))
                    ->options(fn (): array => Role::query()->pluck('name', 'name')->all())
                    ->columns(2),
            ])
            ->action(function (array $data): void {
                Gate::authorize('assignRole', $this->user);

                $this->user->syncRoles($data['roles'] ?? []);

                Notification::make()
                    ->title(__('admin.roles_updated'))
                    ->success()
                    ->send();
            });
    }

    /** The super-admin flag is never part of the generic form state — see UserPolicy::grantSuperAdmin() docblock. */
    public function toggleSuperAdminAction(): Action
    {
        return Action::make('toggleSuperAdmin')
            ->label(fn (): string => $this->user->is_super_admin
                ? __('admin.revoke_super_admin')
                : __('admin.grant_super_admin'))
            ->icon('heroicon-o-shield-exclamation')
            ->color(DaisyColor::ERROR->toFilamentColor())
            ->requiresConfirmation()
            ->visible(fn (): bool => Gate::allows('grantSuperAdmin', $this->user))
            ->action(function (): void {
                Gate::authorize('grantSuperAdmin', $this->user);

                $this->user->forceFill(['is_super_admin' => ! $this->user->is_super_admin])->save();

                Notification::make()
                    ->title($this->user->is_super_admin
                        ? __('admin.super_admin_granted')
                        : __('admin.super_admin_revoked'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Super admins set a new password directly; everyone else authorized to
     * manage users can only trigger the standard password reset link email.
     */
    public function resetPasswordAction(): Action
    {
        if (Gate::allows('updatePasswordDirectly', $this->user)) {
            return Action::make('resetPassword')
                ->label(__('admin.reset_password'))
                ->icon('heroicon-o-key')
                ->schema([
                    Forms\Components\TextInput::make('password')
                        ->label(__('admin.new_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->confirmed(),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->label(__('admin.confirm_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->dehydrated(false),
                ])
                ->action(function (array $data): void {
                    Gate::authorize('updatePasswordDirectly', $this->user);

                    $this->user->update(['password' => Hash::make($data['password'])]);

                    Notification::make()
                        ->title(__('admin.password_reset_success'))
                        ->success()
                        ->send();
                });
        }

        return Action::make('resetPassword')
            ->label(__('admin.send_password_reset_link'))
            ->icon('heroicon-o-key')
            ->requiresConfirmation()
            ->action(function (): void {
                Gate::authorize('sendPasswordResetLink', $this->user);

                Password::sendResetLink(['email' => $this->user->email]);

                Notification::make()
                    ->title(__('admin.password_reset_link_sent'))
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('livewire.admin.users.edit-user');
    }
}
