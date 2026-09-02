<?php

declare(strict_types=1);

namespace App\Panels\Users;

use App\Enums\SystemPermission;
use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\HasPanelActions;
use App\Support\Panels\Contracts\PanelRegion;
use App\Support\Panels\Contracts\ShowPanel;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Two-factor authentication status, with an admin "force disable" escape hatch. */
class SecurityPanel implements HasPanelActions, ShowPanel
{
    use HasPanelMetadata;

    public function key(): string
    {
        return 'security';
    }

    public function order(): int
    {
        return 20;
    }

    public function region(): PanelRegion
    {
        return PanelRegion::Sidebar;
    }

    public function render(Model $subject): View
    {
        return view('panels.users.security', ['user' => $subject]);
    }

    /** @return array<string, \Closure> */
    public function actions(): array
    {
        return [
            'force-disable-2fa' => function (Model $subject): void {
                Gate::authorize(SystemPermission::MANAGE_USERS->value);

                $subject->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ])->save();

                Notification::make()
                    ->title(__('admin.two_factor_force_disabled'))
                    ->success()
                    ->send();
            },
        ];
    }
}
