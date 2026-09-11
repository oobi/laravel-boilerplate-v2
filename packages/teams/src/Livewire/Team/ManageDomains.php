<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Enums\SystemPermission;
use App\Support\Theme\DaisyColor;
use Closure;
use Concise\Teams\Actions\CreateDomain;
use Concise\Teams\Actions\VerifyDomain;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A team's custom domains — the shared section rendered in both the system
 * admin's team Settings tab and the team-area Settings page. Access is runtime
 * authorization: a system admin always manages; otherwise the `MANAGE_DOMAINS`
 * team permission (a sovereign owner holds it via bypass). Owners always at least
 * view (read-only floor); a viewer without manage sees the table without its
 * actions. Only active when `teams.domains.enabled`. See ~dev/TEAMS_DOMAINS_SCOPE.md.
 *
 * @property Team $team
 */
class ManageDomains extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;

        abort_unless($this->canView(), 403);
    }

    /** May the current viewer see this team's domains at all? (Owners always can — read-only floor.) */
    public function canView(): bool
    {
        if (! DomainPolicy::enabled()) {
            return false;
        }

        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || $this->team->isOwnedBy(auth()->user())
            || Gate::allows(TeamAbility::MANAGE_DOMAINS, $this->team);
    }

    /** May the current viewer add/verify/remove domains (vs read-only)? */
    public function canManage(): bool
    {
        if (! DomainPolicy::enabled()) {
            return false;
        }

        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::MANAGE_DOMAINS, $this->team);
    }

    public function addDomainAction(): Action
    {
        return Action::make('addDomain')
            ->label(team_trans('domains.add'))
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => $this->canManage())
            ->modalHeading(team_trans('domains.add_heading', ['name' => $this->team->name]))
            ->modalWidth(Width::Medium)
            ->schema([
                TextInput::make('domain')
                    ->label(team_trans('domains.domain'))
                    ->helperText(team_trans('domains.add_help'))
                    ->required()
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $domain = (string) Domain::normalize(is_string($value) ? $value : '');

                            if (! CreateDomain::isValidHostname($domain)) {
                                $fail(team_trans('domains.invalid'));
                            } elseif (CreateDomain::isReserved($domain)) {
                                $fail(team_trans('domains.reserved'));
                            } elseif (Domain::query()->where('domain', $domain)->exists()) {
                                $fail(team_trans('domains.taken'));
                            }
                        },
                    ]),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canManage(), 403);

                $domain = app(CreateDomain::class)($this->team, $data['domain']);

                Notification::make()
                    ->title(team_trans('domains.added', ['domain' => $domain->domain]))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->team->domains()->getQuery())
            ->defaultSort('domain')
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->label(team_trans('domains.domain'))
                    ->weight('medium')
                    ->icon(fn (Domain $record): ?string => $record->is_primary ? 'heroicon-s-star' : null)
                    ->iconColor(DaisyColor::WARNING->toFilamentColor())
                    ->tooltip(fn (Domain $record): ?string => $record->is_primary ? team_trans('domains.primary') : null),

                Tables\Columns\TextColumn::make('status')
                    ->label(team_trans('domains.status'))
                    ->badge()
                    ->getStateUsing(fn (Domain $record): string => $record->isVerified() ? team_trans('domains.verified') : team_trans('domains.pending'))
                    ->color(fn (Domain $record): string => ($record->isVerified() ? DaisyColor::SUCCESS : DaisyColor::WARNING)->toFilamentColor()),
            ])
            ->recordActions([
                ActionGroup::make([
                    $this->verifyAction(),
                    $this->makePrimaryAction(),
                    $this->removeAction(),
                ])->visible(fn (): bool => $this->canManage()),
            ])
            ->emptyStateHeading(team_trans('domains.empty'))
            ->paginated(false);
    }

    public function render(): View
    {
        return view('teams::livewire.team.manage-domains');
    }

    private function verifyAction(): Action
    {
        return Action::make('verify')
            ->label(team_trans('domains.verify'))
            ->icon('heroicon-o-shield-check')
            ->visible(fn (Domain $record): bool => ! $record->isVerified())
            ->modalHeading(fn (Domain $record): string => $record->domain)
            ->modalDescription(fn (Domain $record): string => team_trans('domains.verify_instructions')
                .' TXT '.$record->verificationHost().' → '.$record->expectedTxtValue())
            ->modalSubmitActionLabel(team_trans('domains.verify'))
            ->action(function (Domain $record): void {
                abort_unless($this->canManage(), 403);

                if (app(VerifyDomain::class)($record)) {
                    Notification::make()->title(team_trans('domains.verified_notice', ['domain' => $record->domain]))->success()->send();
                } else {
                    Notification::make()->title(team_trans('domains.verify_failed'))->warning()->send();
                }
            });
    }

    private function makePrimaryAction(): Action
    {
        return Action::make('makePrimary')
            ->label(team_trans('domains.make_primary'))
            ->icon('heroicon-o-star')
            ->visible(fn (Domain $record): bool => $record->isVerified() && ! $record->is_primary)
            ->action(function (Domain $record): void {
                abort_unless($this->canManage(), 403);

                $this->team->domains()->update(['is_primary' => false]);
                $record->update(['is_primary' => true]);

                Notification::make()->title(team_trans('domains.made_primary', ['domain' => $record->domain]))->success()->send();
            });
    }

    private function removeAction(): Action
    {
        return Action::make('remove')
            ->label(team_trans('domains.remove'))
            ->icon('heroicon-o-trash')
            ->color(DaisyColor::ERROR->toFilamentColor())
            ->requiresConfirmation()
            ->modalDescription(fn (Domain $record): string => team_trans('domains.remove_confirm', ['domain' => $record->domain, 'name' => $this->team->name]))
            ->action(function (Domain $record): void {
                abort_unless($this->canManage(), 403);

                $record->delete();

                Notification::make()->title(team_trans('domains.removed'))->success()->send();
            });
    }
}
