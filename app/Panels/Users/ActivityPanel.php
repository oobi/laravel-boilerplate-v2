<?php

declare(strict_types=1);

namespace App\Panels\Users;

use App\Enums\SystemPermission;
use App\Models\AuditEntry;
use App\Support\Audit\AuditEntryFilters;
use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\ShowPanel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** A person's latest audit entries, as actor and as subject, linking to the full list. */
class ActivityPanel implements ShowPanel
{
    use HasPanelMetadata;

    private const int RECENT = 5;

    public function key(): string
    {
        return 'activity';
    }

    public function order(): int
    {
        return 30;
    }

    public function visible(Model $subject, ?Authenticatable $viewer): bool
    {
        return $viewer !== null && Gate::forUser($viewer)->allows(SystemPermission::VIEW_AUDIT_LOG->value);
    }

    public function render(Model $subject): View
    {
        $entries = AuditEntry::query()
            ->tap(fn ($query) => AuditEntryFilters::forUser($query, $subject->getKey()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT)
            ->get();

        return view('panels.users.activity', ['user' => $subject, 'entries' => $entries]);
    }
}
