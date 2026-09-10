<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Enums\SystemPermission;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamCreationMode;
use Concise\Teams\Livewire\Concerns\HasInviteAction;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A team's pending invitations — searchable, paginated — with resend and
 * revoke, shared by the team area's Invitations page and the system admin's
 * Invitations tab. Authorized for either audience: the `manage teams` system
 * permission, or — when the creation mode allows member invitations — the
 * team's invite ability.
 */
class PendingInvitations extends Component implements HasActions, HasSchemas, HasTable
{
    use HasInviteAction;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;

        abort_unless($this->canManage(), 403);
    }

    /** "Invite" lives in this table's toolbar; whoever may manage invitations may send one. */
    public function canInvite(): bool
    {
        return $this->canManage();
    }

    /** Re-query after the host page sends a new invitation. */
    #[On('team-invitations-updated')]
    public function refreshInvitations(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->team->invitations()->getQuery())
            ->defaultSort('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge()
                    ->placeholder(__('No role')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Sent'))
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('resend')
                        ->label(__('Resend'))
                        ->icon('heroicon-o-envelope')
                        ->action(function (TeamInvitation $record): void {
                            abort_unless($this->canManage(), 403);

                            $record->send();

                            Notification::make()
                                ->title(__('Invitation re-sent to :email', ['email' => $record->email]))
                                ->success()
                                ->send();
                        }),

                    Action::make('revoke')
                        ->label(__('Revoke'))
                        ->icon('heroicon-o-x-circle')
                        ->color(DaisyColor::ERROR->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (TeamInvitation $record): string => __('Revoke the invitation for :email?', ['email' => $record->email]))
                        ->action(function (TeamInvitation $record): void {
                            abort_unless($this->canManage(), 403);

                            $record->delete();
                            $this->dispatch('team-invitations-updated');

                            Notification::make()->title(__('Invitation revoked'))->success()->send();
                        }),
                ]),
            ])
            ->searchPlaceholder(__('Search invitations…'))
            ->emptyStateHeading(__('No pending invitations'))
            ->paginated(config('pagination.page_sizes'))
            ->defaultPaginationPageOption(config('pagination.default_page_size'));
    }

    public function render(): View
    {
        return view('teams::livewire.team.pending-invitations');
    }

    private function canManage(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || (TeamCreationMode::current()->allowsMemberInvitations() && Gate::allows(TeamAbility::INVITE, $this->team));
    }
}
