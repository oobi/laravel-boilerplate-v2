<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Enums\SystemGate;
use App\Livewire\Admin\Roles\Concerns\HasPermissionsSchema;
use App\Models\Role;
use App\Support\Roles\RoleScope;
use App\Support\Roles\RoleScopeRegistry;
use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

/**
 * The single "manage roles" screen: no separate list page, the dropdown
 * switches which role's name/permissions are being edited (see
 * routes/web.php — both `roles.index` and `roles.edit` render this class).
 * One tab per registered RoleScope (core's system scope, plus any an add-on
 * contributes); the tab decides which roles the dropdown lists and which
 * permission vocabulary the form offers.
 */
class ManageRoles extends Component implements HasActions, HasSchemas
{
    use HasPermissionsSchema;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?Role $role = null;

    /** The open RoleScope tab: `?scope=` on the bare route, or the role's own scope when editing one. */
    #[Locked]
    public string $scopeKey = Role::SYSTEM_SCOPE;

    public ?string $selectedRoleId = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(?Role $role = null): void
    {
        Gate::authorize(SystemGate::MANAGE_ROLES);

        // On the bare `/admin/roles` route (no {role} segment) Laravel's container still
        // instantiates an empty, unsaved Role for the nullable type-hint instead of passing
        // null — treat that the same as "no role selected" and fall back to the first one.
        $this->scopeKey = $role?->exists
            ? $role->scope
            : (string) request()->query('scope', RoleScopeRegistry::default()->key());

        // A role whose scope no registered RoleScope owns (an add-on since removed) can't be
        // edited with the right vocabulary — 404 rather than silently use the wrong one.
        abort_if(RoleScopeRegistry::find($this->scopeKey) === null, 404);

        $this->role = $role?->exists ? $role : Role::query()->ofScope($this->scopeKey)->orderBy('name')->first();
        $this->selectedRoleId = $this->role ? (string) $this->role->getKey() : null;

        if (! $this->role) {
            return;
        }

        $this->form->fill([
            'name' => $this->role->name,
            'color' => $this->role->badgeColor()->value,
            ...$this->permissionsStateForRole($this->role),
        ]);
    }

    protected function roleScope(): RoleScope
    {
        return RoleScopeRegistry::find($this->scopeKey) ?? abort(404);
    }

    /** The dropdown navigates rather than swapping state in place, so the URL always reflects the role being edited. */
    public function updatedSelectedRoleId(string $roleId): void
    {
        $this->redirect(route('roles.edit', $roleId));
    }

    public function form(Schema $schema): Schema
    {
        $schema = $schema
            ->components([
                Section::make(__('admin.role_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('admin.role_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('roles', 'name', ignoreRecord: true),

                                Forms\Components\ViewField::make('color')
                                    ->label(__('admin.badge_color'))
                                    ->view('filament.forms.components.role-color-swatches')
                                    ->required(),
                            ]),
                    ]),

                Section::make(__('admin.permissions'))
                    ->schema($this->permissionsSchema()),
            ])
            ->statePath('data');

        return $this->role ? $schema->record($this->role) : $schema;
    }

    public function save(): void
    {
        Gate::authorize(SystemGate::MANAGE_ROLES);

        $data = $this->form->getState();

        $this->role->update([
            'name' => $data['name'],
            'color' => $data['color'],
        ]);

        $this->role->syncPermissions(collect($this->resolvePermissionsFromState($data))
            ->map(fn (string $permission): Permission => Permission::findOrCreate($permission))
            ->all());

        Notification::make()
            ->title(__('admin.role_updated'))
            ->success()
            ->send();
    }

    public function deleteRoleAction(): Action
    {
        return Action::make('deleteRole')
            ->label(__('admin.delete'))
            ->icon('heroicon-o-trash')
            ->color(DaisyColor::ERROR->toFilamentColor())
            ->requiresConfirmation()
            ->visible(fn (): bool => (bool) $this->role)
            ->action(function (): void {
                Gate::authorize(SystemGate::MANAGE_ROLES);

                $this->role->delete();

                Notification::make()
                    ->title(__('admin.role_deleted'))
                    ->success()
                    ->send();

                $this->redirect(route('roles.index', RoleScopeRegistry::routeParameters($this->roleScope())));
            });
    }

    public function render(): View
    {
        $scope = $this->roleScope();
        $scopes = RoleScopeRegistry::all();

        // Tabs only when there's a choice — a single-scope install keeps the plain screen.
        $tabs = collect();

        if ($scopes->count() > 1) {
            $counts = Role::query()->selectRaw('scope, count(*) as aggregate')->groupBy('scope')->pluck('aggregate', 'scope');

            $tabs = $scopes->map(fn (RoleScope $option): array => [
                'label' => $option->label(),
                'href' => route('roles.index', RoleScopeRegistry::routeParameters($option)),
                'active' => $option->key() === $scope->key(),
                'badge' => (int) ($counts[$option->key()] ?? 0),
            ]);
        }

        return view('livewire.admin.roles.manage-roles', [
            'scope' => $scope,
            'tabs' => $tabs,
            'createUrl' => route('roles.create', RoleScopeRegistry::routeParameters($scope)),
            'roles' => Role::query()->ofScope($this->scopeKey)->orderBy('name')->get(),
        ]);
    }
}
