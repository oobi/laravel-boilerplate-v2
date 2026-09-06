<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Livewire\Admin\Roles\Concerns\HasPermissionsSchema;
use App\Models\Role;
use App\Support\Theme\DaisyColor;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

class CreateRole extends Component implements HasSchemas
{
    use HasPermissionsSchema;
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        Gate::authorize('manage roles');

        $this->form->fill([
            'color' => DaisyColor::NEUTRAL->value,
            ...$this->permissionsStateForRole(null),
        ]);
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
                    ]),

                Section::make(__('admin.permissions'))
                    ->schema($this->permissionsSchema()),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        Gate::authorize('manage roles');

        $data = $this->form->getState();

        $role = Role::create([
            'name' => $data['name'],
            'color' => $data['color'],
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
        return view('livewire.admin.roles.create-role');
    }
}
