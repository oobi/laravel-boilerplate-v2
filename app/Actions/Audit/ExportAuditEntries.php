<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditEntry;
use App\Support\Audit\AuditDisplay;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams entries as a CSV, newest first, so a long log never sits in memory.
 * Anything a visitor could have typed reaches the file, so a value a spreadsheet
 * would run as a formula is neutralised.
 */
class ExportAuditEntries
{
    private const int CHUNK = 1000;

    /** Without it, Excel reads the file in the wrong encoding and garbles non-English names. */
    private const string BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /** @param  Builder<AuditEntry>  $entries */
    public function stream(Builder $entries): StreamedResponse
    {
        return response()->streamDownload(function () use ($entries): void {
            // PHP's limit would cut a long file short, with the download already looking fine.
            set_time_limit(0);

            $file = fopen('php://output', 'w');

            fwrite($file, self::BYTE_ORDER_MARK);
            fputcsv($file, $this->headings(), escape: '');

            foreach ($this->newestFirst($entries) as $entry) {
                fputcsv($file, array_map($this->neutraliseFormula(...), $this->row($entry)), escape: '');
            }

            fclose($file);
        }, 'audit-log-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * In the screen's order (when, then id), a chunk at a time. Each chunk starts
     * after the last row of the one before, so a backdated entry sits where its date
     * puts it and a long log is never read with an offset.
     *
     * @param  Builder<AuditEntry>  $entries
     * @return iterable<AuditEntry>
     */
    private function newestFirst(Builder $entries): iterable
    {
        $last = null;

        do {
            $chunk = (clone $entries)
                // The first condition alone bounds the index scan on every database.
                ->when($last !== null, fn (Builder $query) => $query
                    ->where('created_at', '<=', $last->getRawOriginal('created_at'))
                    ->where(fn (Builder $query) => $query
                        ->where('created_at', '<', $last->getRawOriginal('created_at'))
                        ->orWhere('id', '<', $last->getKey())))
                ->reorder()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(self::CHUNK)
                ->get();

            yield from $chunk;

            $last = $chunk->last();
        } while ($chunk->count() === self::CHUNK);
    }

    /** @return list<string> */
    private function headings(): array
    {
        return [
            'id', 'created_at', 'category', 'event', 'description', 'actor_type', 'actor', 'impersonator',
            'subject_type', 'subject_id', 'ip_address', 'user_agent', 'changes', 'properties',
        ];
    }

    /** @return list<string> */
    private function row(AuditEntry $entry): array
    {
        return array_map(fn (mixed $value): string => (string) $value, [
            $entry->getKey(),
            $entry->created_at?->toIso8601String(),
            $entry->log_name,
            $entry->event,
            $entry->description,
            AuditDisplay::actorLabel($entry->actor_type),
            $entry->actor_name,
            $entry->impersonator_name,
            $entry->subject_type,
            $entry->subject_id,
            $entry->ip_address,
            $entry->user_agent,
            $entry->getRawOriginal('attribute_changes'),
            $entry->getRawOriginal('properties'),
        ]);
    }

    /**
     * A leading quote makes a spreadsheet read the cell as text. Invisible characters
     * before the formula don't hide it, and a value too malformed to check is quoted.
     */
    private function neutraliseFormula(string $value): string
    {
        return preg_match('/^[\s\p{Z}\p{C}]*[=+\-@\t\r]/u', $value) !== 0 ? "'".$value : $value;
    }
}
