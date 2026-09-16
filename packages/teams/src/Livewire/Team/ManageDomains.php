<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

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
 * A team's custom domains — the shared table rendered by the team-area Domains
 * page and the system admin's team Settings tab. Access is runtime
 * authorization: a system admin always manages; otherwise the `MANAGE_DOMAINS`
 * team permission (held via a role — ownership grants no bypass). Only active
 * when `teams.domains.custom_domains` (over host mode). See docs/teams-domains.md.
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

    /** May the current viewer see this team's domains at all? One ability — a system admin is answered by TeamPolicy::before(). */
    public function canView(): bool
    {
        return DomainPolicy::customDomainsEnabled() && Gate::allows(TeamAbility::MANAGE_DOMAINS, $this->team);
    }

    /** May the current viewer add/verify/remove domains? Today the same as viewing; kept separate so a read-only grant can be added without touching call sites. */
    public function canManage(): bool
    {
        return $this->canView();
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
                    ->searchable()
                    ->icon(fn (Domain $record): ?string => $record->is_primary ? 'heroicon-s-star' : null)
                    ->iconColor(DaisyColor::WARNING->toFilamentColor())
                    ->tooltip(fn (Domain $record): ?string => $record->is_primary ? team_trans('domains.primary') : null),

                Tables\Columns\TextColumn::make('status')
                    ->label(team_trans('domains.status'))
                    ->badge()
                    ->getStateUsing(fn (Domain $record): string => $record->isVerified() ? team_trans('domains.verified') : team_trans('domains.pending'))
                    ->color(fn (Domain $record): string => ($record->isVerified() ? DaisyColor::SUCCESS : DaisyColor::WARNING)->toFilamentColor()),
            ])
            ->searchPlaceholder(team_trans('domains.search'))
            ->filters([
                // Verified vs pending — the same two states the status badge shows.
                Tables\Filters\SelectFilter::make('status')
                    ->label(team_trans('domains.status'))
                    ->options(self::statusOptions())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'verified' => $query->whereNotNull('verified_at'),
                        'pending' => $query->whereNull('verified_at'),
                        default => $query,
                    }),
            ])
            ->deferFilters(false)
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

    /**
     * Options for the status filter, shared with the Blade header's select.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'verified' => team_trans('domains.verified'),
            'pending' => team_trans('domains.pending'),
        ];
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
