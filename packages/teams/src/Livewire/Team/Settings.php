<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Enums\SystemPermission;
use App\Support\Filament\AdminAction;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Concise\Teams\Support\TeamContext;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The team area's Settings page: team details (name/slug), custom domains
 * when the overlay is on, and the Ownership section — co-owners, transfer and
 * delete, the acts reserved to the primary owner. Open to anyone holding
 * VIEW_SETTINGS or a section's edit permission, and always to the primary
 * owner (TeamPolicy::viewSettings — a responsibility floor, not a bypass: the
 * details form is still read-only without `update`). Editing is gated
 * per-section: `update` for details, `manageDomains` for domains.
 * See ~dev/TEAMS_DOMAINS_SCOPE.md.
 */
#[Layout('teams::layouts.team')]
class Settings extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Team $team;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(Team $team): void
    {
        $this->team = $team;

        Gate::authorize(TeamAbility::VIEW_SETTINGS, $team);

        $this->form->fill(['name' => $team->name, 'slug' => $team->slug]);
    }

    /** See ListMembers::booted() — keep the team scope across Livewire updates. */
    public function booted(): void
    {
        app(TeamContext::class)->set($this->team);
    }

    /** May the viewer edit team details (else the form is read-only)? */
    public function canUpdate(): bool
    {
        return Gate::allows(TeamAbility::UPDATE, $this->team);
    }

    /** Whether to render the domains section (manage-domains permission / system admin). */
    public function canViewDomains(): bool
    {
        return DomainPolicy::enabled() && (
            Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::MANAGE_DOMAINS, $this->team)
        );
    }

    /** Whether to render the ownership section: the primary owner or a system admin. */
    public function canManageOwnership(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::MANAGE_OWNERS, $this->team);
    }

    /** May the viewer delete the team? The primary owner (under self-service creation) or a system admin. */
    public function canDelete(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::DELETE, $this->team);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->record($this->team)
            ->disabled(fn (): bool => ! $this->canUpdate())
            ->components([
                Section::make(team_trans('admin.details'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label(team_trans('admin.name'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('slug')
                                ->label(team_trans('admin.slug'))
                                ->helperText(team_trans(DomainPolicy::enabled() ? 'settings.slug_warning_domains' : 'settings.slug_warning'))
                                ->required()
                                ->maxLength(255)
                                ->alphaDash()
                                ->unique(table: 'teams', column: 'slug', ignoreRecord: true),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Gate::authorize(TeamAbility::UPDATE, $this->team);

        $data = $this->form->getState();

        $this->team->update(['name' => $data['name'], 'slug' => $data['slug']]);

        Notification::make()->title(team_trans('settings.saved'))->success()->send();

        // The slug is the URL — stay on this page even if it changed.
        $this->redirect(route('team.settings', ['team' => $this->team->slug]));
    }

    /**
     * The owner's delete: a soft delete (a system admin can restore it from the
     * Teams trash), confirmed with the member count front and centre.
     */
    public function deleteTeamAction(): Action
    {
        return AdminAction::make('deleteTeam')
            ->label(team_trans('ownership.delete'))
            ->icon('heroicon-o-trash')
            ->soft()
            ->color(DaisyColor::ERROR->toFilamentColor())
            ->visible(fn (): bool => $this->canDelete())
            ->requiresConfirmation()
            ->modalDescription(fn (): string => Team::deleteWarning($this->team))
            ->action(function (): void {
                abort_unless($this->canDelete(), 403);

                $this->team->delete();

                Notification::make()->title(team_trans('ownership.deleted', ['name' => $this->team->name]))->success()->send();

                $this->redirect(route('team.index'));
            });
    }

    public function render(): View
    {
        return view('teams::livewire.team.settings');
    }
}
