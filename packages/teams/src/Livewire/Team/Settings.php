<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Enums\SystemPermission;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Concise\Teams\Support\TeamContext;
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
 * The team area's Settings page — a team owner's self-service home for team
 * details (name/slug) and, when the overlay is on, custom domains. Visible to
 * owners (read-only floor, even under the managed ownership model) and to anyone
 * who can edit a section; editing is gated per-section (`update` for details,
 * `manageDomains` for domains). See ~dev/TEAMS_DOMAINS_SCOPE.md.
 */
#[Layout('teams::layouts.team')]
class Settings extends Component implements HasSchemas
{
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
                                ->helperText(team_trans('settings.slug_warning'))
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

    public function render(): View
    {
        return view('teams::livewire.team.settings');
    }
}
