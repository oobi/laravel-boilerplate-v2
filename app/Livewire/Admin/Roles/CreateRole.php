<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Enums\SystemGate;
use App\Livewire\Admin\Roles\Concerns\HasPermissionsSchema;
use App\Models\Role;
use App\Support\Roles\RoleScope;
use App\Support\Roles\RoleScopeRegistry;
use App\Support\Theme\DaisyColor;
use App\Support\TwoFactor\GracePeriod;
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

class CreateRole extends Component implements HasSchemas
{
    use HasPermissionsSchema;
    use InteractsWithSchemas;

    /** The RoleScope the new role belongs to (`?scope=`, from the Roles screen's active tab). */
    #[Locked]
    public string $scopeKey = Role::SYSTEM_SCOPE;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        Gate::authorize(SystemGate::MANAGE_ROLES);

        $this->scopeKey = (string) request()->query('scope', RoleScopeRegistry::default()->key());

        abort_if(RoleScopeRegistry::find($this->scopeKey) === null, 404);

        $this->form->fill([
            'color' => DaisyColor::NEUTRAL->value,
            'requires_two_factor' => false,
            ...$this->permissionsStateForRole(null),
        ]);
    }

    protected function roleScope(): RoleScope
    {
        return RoleScopeRegistry::find($this->scopeKey) ?? abort(404);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.role_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('admin.role_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('roles', 'name'),

                                Forms\Components\ViewField::make('color')
                                    ->label(__('admin.badge_color'))
                                    ->view('filament.forms.components.role-color-swatches')
                                    ->required(),
                            ]),

                        // Only system roles are assigned to users directly, so only they can carry the mandate.
                        Forms\Components\Toggle::make('requires_two_factor')
                            ->label(__('admin.require_two_factor'))
                            ->helperText(__('admin.require_two_factor_help', ['days' => GracePeriod::graceDays()]))
                            ->visible(fn (): bool => $this->scopeKey === Role::SYSTEM_SCOPE),
                    ]),

                Section::make(__('admin.permissions'))
                    ->schema($this->permissionsSchema()),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        Gate::authorize(SystemGate::MANAGE_ROLES);

        $data = $this->form->getState();

        $role = Role::create([
            'name' => $data['name'],
            'color' => $data['color'],
            'requires_two_factor' => $this->scopeKey === Role::SYSTEM_SCOPE && ($data['requires_two_factor'] ?? false),
            ...$this->roleScope()->attributes(),
        ]);

        $role->givePermissionTo(collect($this->resolvePermissionsFromState($data))
            ->map(fn (string $permission): Permission => Permission::findOrCreate($permission))
            ->all());

        Notification::make()
            ->title(__('admin.role_created'))
            ->success()
            ->send();

        $this->redirect(route('roles.edit', $role));
    }

    public function render(): View
    {
        $scope = $this->roleScope();

        return view('livewire.admin.roles.create-role', [
            // Only worth saying which family the role joins when there's more than one.
            'description' => RoleScopeRegistry::all()->count() > 1 ? $scope->description() : null,
            'cancelUrl' => route('roles.index', RoleScopeRegistry::routeParameters($scope)),
        ]);
    }
}
