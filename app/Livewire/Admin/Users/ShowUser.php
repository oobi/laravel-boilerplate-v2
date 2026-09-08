<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Enums\SystemPermission;
use App\Livewire\Concerns\ConfirmsPassword;
use App\Models\Role;
use App\Models\User;
use App\Support\Filament\AdminAction;
use App\Support\Panels\Contracts\HasGuardedActions;
use App\Support\Panels\Contracts\HasPanelActions;
use App\Support\Panels\Contracts\PanelRegion;
use App\Support\Panels\Contracts\ShowPanel;
use App\Support\Panels\Registry\PanelRegistry;
use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ShowUser extends Component implements HasActions, HasSchemas
{
    use ConfirmsPassword;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public User $user;

    public function mount(User $user): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);

        $this->user = $user;
    }

    /** The one place a viewer can edit this user from — see docs/panels.md "UX flow". */
    public function canEditUser(): bool
    {
        return Gate::allows('update', $this->user);
    }

    public function canImpersonateUser(): bool
    {
        return Gate::allows('impersonate', $this->user);
    }

    public function userInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->user)
            ->components([
                Section::make(__('admin.user_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('admin.name')),

                                TextEntry::make('email')
                                    ->label(__('admin.email'))
                                    ->icon('heroicon-o-envelope'),

                                TextEntry::make('role_summary')
                                    ->label(__('admin.roles'))
                                    ->state(fn (User $record): array => $record->is_super_admin
                                        ? [__('admin.super_admin')]
                                        : ($record->roles->pluck('name')->all() ?: [__('admin.no_roles')]))
                                    ->badge(fn (User $record): bool => $record->is_super_admin || $record->roles->isNotEmpty())
                                    ->color(fn (string $state, User $record): ?string => $state === __('admin.super_admin')
                                        ? DaisyColor::ERROR->toFilamentColor()
                                        : $record->roles->firstWhere('name', $state)?->badgeColor()->toFilamentColor()),

                                TextEntry::make('status')
                                    ->label(__('admin.status'))
                                    ->badge(),

                                TextEntry::make('created_at')
                                    ->label(__('admin.joined'))
                                    ->dateTime(),

                                TextEntry::make('last_login_at')
                                    ->label(__('admin.last_login'))
                                    ->dateTime()
                                    ->placeholder(__('admin.never')),
                            ]),
                    ]),
            ]);
    }

    /**
     * The user's administrative operations all live here, on the Show page's
     * Actions card — one place to act on a user, rather than scattered across
     * the list, Show and Edit. Each is independently authorized for visibility
     * and again inside its closure at the write boundary.
     */
    public function impersonateAction(): Action
    {
        return AdminAction::make('impersonate')
            ->label(__('admin.impersonate_user'))
            ->icon('heroicon-o-finger-print')
            ->color(DaisyColor::WARNING->toFilamentColor())
            ->visible(fn (): bool => Gate::allows('impersonate', $this->user))
            ->url(fn (): string => route('users.impersonate', $this->user->id));
    }

    /**
     * The user's access: role assignment (its own `assignRole` ability, never
     * implied by a generic "manage users" grant) plus the super-admin flag as a
     * guarded toggle (`grantSuperAdmin`, and never on yourself). Each side is
     * gated for visibility and re-authorized at the write boundary, so a viewer
     * who holds only one of the two abilities sees and can change only that.
     * Active status is deliberately NOT here — it's a field on the edit form.
     * See UserPolicy::assignRole() / grantSuperAdmin() docblocks.
     */
    public function manageRolesAction(): Action
    {
        return AdminAction::make('manageRoles')
            ->label(__('admin.manage_roles'))
            ->icon('heroicon-o-shield-check')
            // Filament defaults schema modals to 4xl; a role checklist doesn't
            // need that. A dev with a very long role list can widen it again.
            // (Sticky header/footer is applied globally in AppServiceProvider.)
            ->modalWidth(Width::Large)
            ->visible(fn (): bool => Gate::allows('assignRole', $this->user) || Gate::allows('grantSuperAdmin', $this->user))
            ->fillForm(fn (): array => [
                'roles' => $this->user->roles->pluck('name')->all(),
                'is_super_admin' => $this->user->is_super_admin,
            ])
            ->schema([
                Forms\Components\Toggle::make('is_super_admin')
                    ->label(__('admin.super_admin'))
                    ->helperText(__('admin.super_admin_toggle_warning'))
                    ->visible(fn (): bool => Gate::allows('grantSuperAdmin', $this->user)),

                // Only shown when both sections are present, so it never floats alone.
                Html::make('<hr class="border-base-300">')
                    ->visible(fn (): bool => Gate::allows('grantSuperAdmin', $this->user) && Gate::allows('assignRole', $this->user)),

                Forms\Components\CheckboxList::make('roles')
                    ->label(__('admin.roles'))
                    ->options(fn (): array => Role::query()->pluck('name', 'name')->all())
                    ->columns(2)
                    ->visible(fn (): bool => Gate::allows('assignRole', $this->user)),
            ])
            ->action(function (array $data): void {
                if (Gate::allows('assignRole', $this->user) && array_key_exists('roles', $data)) {
                    Gate::authorize('assignRole', $this->user);

                    $this->user->syncRoles($data['roles'] ?? []);
                }

                if (Gate::allows('grantSuperAdmin', $this->user)
                    && array_key_exists('is_super_admin', $data)
                    && (bool) $data['is_super_admin'] !== $this->user->is_super_admin
                ) {
                    Gate::authorize('grantSuperAdmin', $this->user);

                    $this->user->forceFill(['is_super_admin' => (bool) $data['is_super_admin']])->save();
                }

                Notification::make()
                    ->title(__('admin.roles_updated'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Super admins set a new password directly; everyone else authorized to
     * manage users can only trigger the standard password reset link email.
     * Each branch guards its own visibility so the button never shows to a
     * viewer who could only reach the Show page to look.
     */
    public function resetPasswordAction(): Action
    {
        if (Gate::allows('updatePasswordDirectly', $this->user)) {
            return AdminAction::make('resetPassword')
                ->label(__('admin.reset_password'))
                ->icon('heroicon-o-key')
                // Two stacked password fields don't need Filament's default 4xl.
                ->modalWidth(Width::Medium)
                ->visible(fn (): bool => Gate::allows('updatePasswordDirectly', $this->user))
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

                    // Changing the stored hash makes every live session the
                    // target still has elsewhere fail AuthenticateSession's hash
                    // check and log out on its next request — no explicit
                    // logoutOtherDevices() is possible here (that acts on the
                    // acting guard, and the admin is not the target).
                    $this->user->update(['password' => Hash::make($data['password'])]);

                    Notification::make()
                        ->title(__('admin.password_reset_success'))
                        ->success()
                        ->send();
                });
        }

        return AdminAction::make('resetPassword')
            ->label(__('admin.send_password_reset_link'))
            ->icon('heroicon-o-key')
            ->visible(fn (): bool => Gate::allows('sendPasswordResetLink', $this->user))
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
        return view('livewire.admin.users.show-user');
    }

    /** @return Collection<int, ShowPanel> */
    public function panelsFor(string $region): Collection
    {
        return PanelRegistry::showPanels('users.show', $this->user, Auth::user())
            ->filter(fn (ShowPanel $panel): bool => $panel->region() === PanelRegion::from($region));
    }

    /**
     * Generic glue between a panel's own view (e.g. "force disable 2FA") and its
     * action closure. Actions the panel flags via HasGuardedActions run only
     * after the admin re-enters their password in the inline prompt.
     */
    public function callPanelAction(string $panelKey, string $action): void
    {
        $callback = $this->resolvePanelAction($panelKey, $action);

        if ($this->panelActionRequiresPassword($panelKey, $action)) {
            $this->startConfirmingPassword('panelAction', [$panelKey, $action]);

            return;
        }

        $callback($this->user);
    }

    protected function dispatchConfirmedAction(string $action, array $arguments): void
    {
        match ($action) {
            'panelAction' => $this->resolvePanelAction($arguments[0], $arguments[1])($this->user),
            default => abort(403),
        };
    }

    /** @return \Closure(User): void */
    private function resolvePanelAction(string $panelKey, string $action): \Closure
    {
        $panel = PanelRegistry::find('users.show', $panelKey, $this->user, Auth::user());

        abort_unless($panel instanceof HasPanelActions, 404);

        $callback = $panel->actions()[$action] ?? null;

        abort_unless($callback !== null, 404);

        return $callback;
    }

    private function panelActionRequiresPassword(string $panelKey, string $action): bool
    {
        $panel = PanelRegistry::find('users.show', $panelKey, $this->user, Auth::user());

        return $panel instanceof HasGuardedActions
            && in_array($action, $panel->guardedActions(), true);
    }
}
