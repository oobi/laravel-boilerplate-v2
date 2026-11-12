<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The audit list's quick views: tabs over the categories. A category can sit
 * in more than one view (Access is both a change and a security matter). A
 * project's own category joins a view through AuditDisplay::registerCategory();
 * one that joins none shows under All only.
 */
enum AuditView: string
{
    case ALL = 'all';

    /** What people changed: records and privileged acts. Leaves out logins and logouts. */
    case CHANGES = 'changes';

    /** Logins, account security and privileged acts. */
    case SECURITY = 'security';

    /** Emails the app sent, once they are logged. */
    case EMAIL = 'email';

    /** Scheduled tasks and queued jobs. */
    case SYSTEM = 'system';

    /** Logged under the category of the same name, once email logging exists. */
    public const string EMAIL_CATEGORY = 'email';

    public function label(): string
    {
        return __("audit.quick_view.{$this->value}");
    }

    /**
     * The categories this view shows; empty for All, which shows everything.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        $core = match ($this) {
            self::ALL => [],
            self::CHANGES => [AuditCategory::DATA->value, AuditCategory::ACCESS->value],
            self::SECURITY => [AuditCategory::AUTH->value, AuditCategory::SECURITY->value, AuditCategory::ACCESS->value],
            self::EMAIL => [self::EMAIL_CATEGORY],
            self::SYSTEM => [AuditCategory::SYSTEM->value],
        };

        return $this === self::ALL ? [] : array_values(array_unique([...$core, ...AuditDisplay::categoriesInView($this)]));
    }

    /** @param  Builder<AuditEntry>  $query */
    public function apply(Builder $query): void
    {
        if ($this !== self::ALL) {
            $query->whereIn('log_name', $this->categories());
        }
    }

    /**
     * How many entries each view holds, from the number in each category.
     *
     * @param  Collection<string, int>  $perCategory  category => count
     * @return array<string, int> view value => count
     */
    public static function counts(Collection $perCategory): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $view): array => [$view->value => $view === self::ALL
                ? (int) $perCategory->sum()
                : (int) $perCategory->only($view->categories())->sum()])
            ->all();
    }
}
