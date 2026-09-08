<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\User;
use App\Support\Panels\Registry\PanelRegistry;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class EditUser extends Component implements HasSchemas
{
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

    public function render(): View
    {
        return view('livewire.admin.users.edit-user');
    }
}
