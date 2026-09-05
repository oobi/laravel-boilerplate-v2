<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Panels\Contracts\HasPanelActions;
use App\Support\Panels\Contracts\PanelRegion;
use App\Support\Panels\Contracts\ShowPanel;
use App\Support\Panels\Registry\PanelRegistry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ShowUser extends Component implements HasSchemas
{
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

                                TextEntry::make('system_role')
                                    ->label(__('admin.system_role'))
                                    ->badge()
                                    ->placeholder(__('admin.no_system_role')),

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

    /** Generic glue between a panel's own view (e.g. "force disable 2FA") and its action closure. */
    public function callPanelAction(string $panelKey, string $action): void
    {
        $panel = PanelRegistry::find('users.show', $panelKey, $this->user, Auth::user());

        abort_unless($panel instanceof HasPanelActions, 404);

        $callback = $panel->actions()[$action] ?? null;

        abort_unless($callback !== null, 404);

        $callback($this->user);
    }
}
