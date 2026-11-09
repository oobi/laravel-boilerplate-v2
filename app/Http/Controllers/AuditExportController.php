<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\ExportAuditEntries;
use App\Models\AuditEntry;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use App\Support\Audit\AuditEntryFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The audit log as a CSV, holding what the screen's filters show. A POST: it
 * records an entry of its own, and only a CSRF-protected form may cause that.
 */
class AuditExportController extends Controller
{
    public function __invoke(Request $request, ExportAuditEntries $export): StreamedResponse
    {
        /** @var array{category?: ?string, event?: ?string, actor_type?: ?string, date?: ?string, search?: ?string, user?: ?string} $filters */
        $filters = $request->validate([
            'category' => ['nullable', 'string'],
            'event' => ['nullable', 'string'],
            'actor_type' => ['nullable', 'string'],
            'date' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'user' => ['nullable', 'regex:'.AuditEntryFilters::USER_ID],
        ]);

        Audit::record('audit.exported', properties: ['filters' => array_filter($filters, 'filled')], category: AuditCategory::ACCESS);

        return $export->stream(AuditEntryFilters::apply(AuditEntry::query(), $filters));
    }
}
