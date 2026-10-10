<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Enums\UserAbility;
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
        // A trashed user resolves (for restore) but is restored, not edited.
        abort_if($user->trashed(), 404);

        $this->user = $user;

        Gate::authorize(UserAbility::UPDATE, $this->user);

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
        abort_if($this->user->trashed(), 404);
        Gate::authorize(UserAbility::UPDATE, $this->user);

        // Disabled fields (e.g. a self-edit's active toggle) are excluded from
        // getState() by Filament, so this trusts whatever the registered panels expose.
        $data = $this->form->getState();

        // Sensitive fields are re-checked at the write boundary against their own
        // ability, independent of whatever the form schema disables/excludes —
        // a generic "update" grant must never silently authorize a status change.
        if (array_key_exists('active', $data) && $data['active'] !== $this->user->active) {
            Gate::authorize(UserAbility::TOGGLE_ACTIVE, $this->user);
        }

        abort_if($this->isChangingOwnEmail($data), 403);

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

    /**
     * Your own email changes on your profile, which asks for the current
     * password: refused here even if a form section exposes the field.
     *
     * @param  array<string, mixed>  $data
     */
    private function isChangingOwnEmail(array $data): bool
    {
        return $this->user->is(Auth::user())
            && array_key_exists('email', $data)
            && User::normalizeEmail($data['email']) !== $this->user->email;
    }
}
