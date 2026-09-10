<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Models\Team;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * System admin: a team's Overview tab — details and statistics. The other
 * tabs (Members, Invitations, Settings) are their own routes/components, one
 * per page like the profile screens. Gated by the `manage teams` system
 * permission, never by membership.
 */
class ShowTeam extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Team $team;

    public function mount(Team $team): void
    {
        Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

        $this->team = $team;
    }

    public function teamInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->team)
            ->components([
                Section::make(__(':label information', ['label' => config('teams.labels.singular', 'Team')]))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('Name')),

                                TextEntry::make('owner.name')
                                    ->label(__('Primary owner'))
                                    ->url(fn (Team $record): ?string => $record->owner ? route('users.show', $record->owner) : null)
                                    ->helperText(fn (Team $record): ?string => $record->owner?->email),

                                TextEntry::make('slug')
                                    ->label(__('Slug')),

                                TextEntry::make('active')
                                    ->label(__('admin.status'))
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? __('Active') : __('Inactive'))
                                    ->color(fn (bool $state): string => ($state ? DaisyColor::SUCCESS : DaisyColor::NEUTRAL)->toFilamentColor()),

                                TextEntry::make('created_at')
                                    ->label(__('Created'))
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label(__('Last updated'))
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.show-team', [
            'memberCount' => $this->team->users()->count(),
            'ownerCount' => $this->team->ownerIds()->count(),
            'suspendedCount' => $this->team->users()->wherePivotNotNull('suspended_at')->count(),
            'invitationCount' => $this->team->invitations()->count(),
        ]);
    }
}
