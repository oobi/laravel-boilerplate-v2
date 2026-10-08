<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The browser tab's title, most specific first: "Edit · Jane Doe · Users ·
 * App", so tabs, history, bookmarks and screen readers can tell pages apart
 * (WCAG 2.4.2). The page is a Livewire title when one is set, else the view's
 * `page-title` section (the one the breadcrumbs use); the context is what the
 * page sits under, e.g. the breadcrumb trail or the team.
 */
final class PageTitle
{
    /**
     * @param  list<?string>  $context  Most specific first.
     */
    public static function for(?string $title = null, array $context = []): string
    {
        $parts = array_map(
            fn (?string $part): string => trim((string) $part),
            [$title ?: Breadcrumbs::sectionText('page-title'), ...$context, (string) config('app.name', 'Laravel')],
        );

        return implode(' · ', array_filter($parts));
    }

    /**
     * The context a breadcrumb trail gives: its crumbs between the root and
     * the page, nearest first.
     *
     * @param  list<array{label: string, url: ?string}>  $crumbs
     * @return list<string>
     */
    public static function trailContext(array $crumbs): array
    {
        return array_reverse(array_column(array_slice($crumbs, 1, -1), 'label'));
    }
}
