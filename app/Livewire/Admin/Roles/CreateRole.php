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

class CreateRole extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        Gate::authorize('manage roles');

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.role_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin.role_name'))
                            ->required()
                            ->maxLength(255)
                            ->unique('roles', 'name'),

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

    public function create(): void
    {
        Gate::authorize('manage roles');

        $data = $this->form->getState();

        $role = Role::create(['name' => $data['name']]);

        $role->givePermissionTo(collect($data['permissions'] ?? [])
            ->map(fn (string $permission): Permission => Permission::findOrCreate($permission))
            ->all());

        Notification::make()
            ->title(__('admin.role_created'))
            ->success()
            ->send();

        $this->redirect(route('roles.index'));
    }

    public function render(): View
    {
        return view('livewire.admin.roles.create-role');
    }
}
