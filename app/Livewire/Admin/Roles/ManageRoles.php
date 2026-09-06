<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Livewire\Admin\Roles\Concerns\HasPermissionsSchema;
use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The single "manage roles" screen: no separate list page, the dropdown
 * switches which role's name/permissions are being edited (see
 * routes/web.php — both `roles.index` and `roles.edit` render this class).
 */
class ManageRoles extends Component implements HasActions, HasSchemas
{
    use HasPermissionsSchema;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?Role $role = null;

    public ?string $selectedRoleId = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(?Role $role = null): void
    {
        Gate::authorize('manage roles');

        // On the bare `/admin/roles` route (no {role} segment) Laravel's container still
        // instantiates an empty, unsaved Role for the nullable type-hint instead of passing
        // null — treat that the same as "no role selected" and fall back to the first one.
        $this->role = $role?->exists ? $role : Role::query()->orderBy('name')->first();
        $this->selectedRoleId = $this->role ? (string) $this->role->getKey() : null;

        if (! $this->role) {
            return;
        }

        $this->form->fill([
            'name' => $this->role->name,
            ...$this->permissionsStateForRole($this->role),
        ]);
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
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin.role_name'))
                            ->required()
                            ->maxLength(255)
                            ->unique('roles', 'name', ignoreRecord: true),
                    ]),

                Section::make(__('admin.permissions'))
                    ->schema($this->permissionsSchema()),
            ])
            ->statePath('data');

        return $this->role ? $schema->record($this->role) : $schema;
    }

    public function save(): void
    {
        Gate::authorize('manage roles');

        $data = $this->form->getState();

        $this->role->update(['name' => $data['name']]);

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
                Gate::authorize('manage roles');

                $this->role->delete();

                Notification::make()
                    ->title(__('admin.role_deleted'))
                    ->success()
                    ->send();

                $this->redirect(route('roles.index'));
            });
    }

    public function render(): View
    {
        return view('livewire.admin.roles.manage-roles', [
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }
}
