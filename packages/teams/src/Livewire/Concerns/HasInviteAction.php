<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Concerns;

use App\Models\User;
use Closure;
use Concise\Teams\Actions\InviteMember;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamCoverage;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use InvalidArgumentException;

/**
 * The "invite by email" modal, in the pending invitations table's toolbar.
 * The host exposes `$team` and decides canInvite(); an invitation grants at
 * most one role regardless of the project's roles-per-member setting.
 *
 * @property Team $team
 */
trait HasInviteAction
{
    abstract public function canInvite(): bool;

    public function inviteAction(): Action
    {
        return Action::make('invite')
            ->label(team_trans('invitations.invite'))
            ->icon('heroicon-o-envelope')
            ->modalHeading(team_trans('invitations.invite_heading', ['name' => $this->team->name]))
            ->modalWidth(Width::Medium)
            ->visible(fn (): bool => $this->canInvite())
            ->schema([
                TextInput::make('email')
                    ->label(team_trans('invitations.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->mutateStateForValidationUsing(fn (?string $state): ?string => User::normalizeEmail($state))
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if ($this->team->users()->where('email', User::normalizeEmail($value))->exists()) {
                                $fail(team_trans('invitations.already_member'));
                            }
                        },
                    ]),

                Select::make('role')
                    ->label(team_trans('invitations.role'))
                    ->options(fn (): array => Team::availableRoles()->orderBy('name')->pluck('name', 'name')->all())
                    // Only a role the inviter holds everything of (TeamCoverage); InviteMember checks again.
                    ->disableOptionWhen(fn (string $value): bool => ! TeamCoverage::coversRoleNamed(auth()->user(), $this->team, $value))
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (filled($value) && ! TeamCoverage::coversRoleNamed(auth()->user(), $this->team, $value)) {
                                $fail(team_trans('invitations.role_not_allowed', ['role' => $value]));
                            }
                        },
                    ])
                    ->placeholder(team_trans('members.no_role')),
            ])
            ->action(function (array $data, Action $action): void {
                abort_unless($this->canInvite(), 403);

                // InviteMember is the write boundary; anything it refuses that the form
                // didn't catch becomes a message rather than an error page.
                try {
                    $invitation = app(InviteMember::class)($this->team, $data['email'], $data['role'] ?: null, auth()->user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    $action->halt();
                }

                $this->dispatch('team-invitations-updated');

                Notification::make()
                    ->title(team_trans('invitations.sent_to', ['email' => $invitation->email]))
                    ->success()
                    ->send();
            });
    }
}
