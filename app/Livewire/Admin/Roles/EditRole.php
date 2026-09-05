<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Enums\SystemPermission;
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

class EditRole extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Role $role;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(Role $role): void
    {
        Gate::authorize('manage roles');

        $this->role = $role;

        $this->form->fill([
            'name' => $this->role->name,
            'permissions' => $this->role->permissions->pluck('name')->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->record($this->role)
            ->components([
                Section::make(__('admin.role_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin.role_name'))
                            ->required()
                            ->maxLength(255)
                            ->unique('roles', 'name', ignoreRecord: true),

                        Forms\Components\CheckboxList::make('permissions')
                            ->label(__('admin.permissions'))
                            ->options(fn (): array => collect(SystemPermission::cases())
                                ->mapWithKeys(fn (SystemPermission $permission): array => [$permission->value => $permission->label()])
                                ->all())
                            ->columns(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Gate::authorize('manage roles');

        $data = $this->form->getState();

        $this->role->update(['name' => $data['name']]);

        $this->role->syncPermissions(collect($data['permissions'] ?? [])
            ->map(fn (string $permission): Permission => Permission::findOrCreate($permission))
            ->all());

        Notification::make()
            ->title(__('admin.role_updated'))
            ->success()
            ->send();

        $this->redirect(route('roles.index'));
    }

    public function render(): View
    {
        return view('livewire.admin.roles.edit-role');
    }
}
