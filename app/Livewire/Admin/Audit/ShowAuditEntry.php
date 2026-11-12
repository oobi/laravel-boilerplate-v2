<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Audit;

use App\Enums\SystemPermission;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditDisplay;
use App\Support\Audit\LogAuditEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/** One line of the audit log: who, what, on what, from where, and what changed. */
class ShowAuditEntry extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** A changed value is highlighted only when it's short and mostly the same as before. */
    private const int HIGHLIGHT_MAX_LENGTH = 200;

    public AuditEntry $entry;

    public function mount(AuditEntry $entry): void
    {
        Gate::authorize(SystemPermission::VIEW_AUDIT_LOG->value);

        $this->entry = $entry;
    }

    /** The event in words, else the description. */
    public function heading(): string
    {
        return $this->entry->event !== null ? Audit::describe($this->entry->event) : (string) $this->entry->description;
    }

    public function summary(): ?string
    {
        return AuditDisplay::summary($this->entry);
    }

    /** The code a developer searches for, when it says more than the heading. */
    public function eventCode(): ?string
    {
        $event = $this->entry->event;

        return $event === null || strcasecmp(Audit::describe($event), $event) === 0 ? null : $event;
    }

    /** Everything else from the same browser session, oldest first; null for an entry made outside one. */
    public function sessionUrl(): ?string
    {
        return $this->entry->session_trail === null ? null : route('audit.index', ['session' => $this->entry->session_trail]);
    }

    public function contextInfolist(Schema $schema): Schema
    {
        $subject = AuditDisplay::subject($this->entry);

        return $schema
            ->record($this->entry)
            ->components([
                Section::make(__('audit.event_context'))
                    // Beside the details a narrow column; alone, it spreads across the page.
                    ->columns($this->hasDetails() ? 1 : ['md' => 2, 'lg' => 3])
                    ->schema([
                        TextEntry::make('created_at')->label(__('audit.occurred_at'))->dateTime(),
                        TextEntry::make('log_name')
                            ->label(__('audit.category'))
                            ->formatStateUsing(fn (string $state): string => AuditDisplay::categoryLabel($state)),
                        TextEntry::make('subject_label')
                            ->label(__('audit.subject'))
                            ->state($subject['label'] ?? null)
                            ->url($subject['url'] ?? null)
                            ->extraAttributes(['class' => 'wrap-anywhere'])
                            ->placeholder(__('audit.none')),
                        ViewEntry::make('actor_name')
                            ->label(__('audit.performed_by'))
                            ->view('filament.infolists.entries.audit-performed-by', ['performer' => $this->performer()]),
                        TextEntry::make('ip_address')
                            ->label(__('audit.ip_address'))
                            ->fontFamily('mono')
                            ->placeholder(__('audit.none')),
                        TextEntry::make('impersonator_name')
                            ->label(__('audit.impersonated_by'))
                            ->extraAttributes(['class' => 'wrap-anywhere'])
                            ->placeholder(__('audit.none')),
                    ]),
            ]);
    }

    /** Whether the main column has anything to show; the sidebar always does. */
    public function hasDetails(): bool
    {
        return $this->hasChanges() || $this->hasProperties() || $this->hasRequestDetails();
    }

    public function hasChanges(): bool
    {
        return $this->changes()->isNotEmpty();
    }

    public function hasProperties(): bool
    {
        return $this->properties()->isNotEmpty();
    }

    public function hasRequestDetails(): bool
    {
        return filled($this->entry->user_agent);
    }

    /** What changed, anything else recorded, and the request: the main column. */
    public function detailInfolist(Schema $schema): Schema
    {
        $changes = $this->changes();
        $properties = $this->properties();

        return $schema
            ->record($this->entry)
            ->components([
                Section::make(__('audit.changes'))
                    ->afterHeader([Text::make(trans_choice('audit.field_count', $changes->count()))->badge()->color('gray')])
                    ->visible($this->hasChanges())
                    ->schema([
                        SchemaView::make('livewire.admin.audit.changes-table')->viewData([
                            'changes' => $changes,
                            'hasBefore' => $this->hasBefore(),
                            'hasAfter' => $this->hasAfter(),
                        ]),
                    ]),

                Section::make(__('audit.event_data'))
                    ->visible($this->hasProperties())
                    ->schema([
                        SchemaView::make('livewire.admin.audit.properties-table')->viewData(['properties' => $properties]),
                    ]),

                Section::make(__('audit.request_details'))
                    ->visible($this->hasRequestDetails())
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('user_agent')
                            ->label(__('audit.user_agent'))
                            ->fontFamily('mono')
                            ->extraAttributes(['class' => 'wrap-anywhere']),
                    ]),
            ]);
    }

    /**
     * Who did it: their name (a link for someone who may open people) and what
     * kind of actor they were.
     *
     * @return array{name: string, type: string, url: string|null, initials: bool}
     */
    public function performer(): array
    {
        $person = $this->entry->causer instanceof User ? $this->entry->causer : null;

        return [
            'name' => $this->entry->actor_name ?: AuditDisplay::actorLabel($this->entry->actor_type),
            'type' => AuditDisplay::actorLabel($this->entry->actor_type),
            // A soft-deleted person is linked too: their page still opens, as it does from "Applies to".
            'url' => $person !== null && Gate::allows(SystemPermission::VIEW_USERS->value) ? route('users.show', $person) : null,
            'initials' => filled($this->entry->actor_name),
        ];
    }

    /**
     * Each changed attribute, before and after. A created record has no "before",
     * and a deleted one no "after", so those columns go.
     *
     * @return Collection<int, array{field: string, label: string, before: array{text: string, quiet: bool}, after: array{text: string, quiet: bool, changed: array{0: string, 1: string, 2: string}|null}}>
     */
    public function changes(): Collection
    {
        $changes = collect($this->entry->attribute_changes ?? []);
        $before = collect($changes->get('old', []));
        $after = collect($changes->get('attributes', []));

        return $before->keys()->merge($after->keys())->unique()->sort()->values()
            ->map(fn (string $field): array => [
                'field' => $field,
                'label' => AuditDisplay::fieldLabel($field),
                'before' => $this->shown($before->get($field)),
                'after' => [
                    ...$this->shown($after->get($field)),
                    'changed' => $this->highlight($before->get($field), $after->get($field)),
                ],
            ]);
    }

    public function hasBefore(): bool
    {
        return filled(data_get($this->entry->attribute_changes, 'old'));
    }

    public function hasAfter(): bool
    {
        return filled(data_get($this->entry->attribute_changes, 'attributes'));
    }

    /**
     * Whatever else the entry recorded. A list or a set of values reads as
     * indented JSON.
     *
     * @return Collection<int, array{name: string, label: string, value: string, structured: bool}>
     */
    public function properties(): Collection
    {
        return collect($this->entry->properties ?? [])
            ->map(fn (mixed $value, int|string $name): array => [
                'name' => (string) $name,
                'label' => AuditDisplay::fieldLabel((string) $name),
                'value' => is_array($value)
                    ? (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
                    : AuditDisplay::value($value),
                'structured' => is_array($value),
            ])
            ->values();
    }

    public function render(): View
    {
        return view('livewire.admin.audit.show-audit-entry');
    }

    /**
     * A value as shown, and whether it is a stand-in (not set, hidden) rather than
     * the value itself.
     *
     * @return array{text: string, quiet: bool}
     */
    private function shown(mixed $value): array
    {
        return match (true) {
            $value === null => ['text' => __('audit.empty_value'), 'quiet' => true],
            $value === LogAuditEntry::REDACTED => ['text' => __('audit.hidden_value'), 'quiet' => true],
            default => ['text' => AuditDisplay::value($value), 'quiet' => false],
        };
    }

    /**
     * Characters as a reader sees them, so an accent typed as its own mark stays
     * with its letter. Code points where the intl extension isn't installed.
     *
     * @return list<string>
     */
    private function characters(string $value): array
    {
        $characters = function_exists('grapheme_str_split') ? grapheme_str_split($value) : false;

        return $characters === false ? mb_str_split($value) : $characters;
    }

    /**
     * The part of the new value that changed, between what it kept at the start
     * and the end, for a short value that mostly stayed the same.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function highlight(mixed $before, mixed $after): ?array
    {
        if (! is_string($before) || ! is_string($after) || $before === LogAuditEntry::REDACTED || $after === LogAuditEntry::REDACTED) {
            return null;
        }

        $old = $this->characters($before);
        $new = $this->characters($after);

        if ($old === [] || $new === [] || count($new) > self::HIGHLIGHT_MAX_LENGTH) {
            return null;
        }

        $start = 0;

        while ($start < count($old) && $start < count($new) && $old[$start] === $new[$start]) {
            $start++;
        }

        $end = 0;

        while ($end < count($old) - $start && $end < count($new) - $start && $old[count($old) - 1 - $end] === $new[count($new) - 1 - $end]) {
            $end++;
        }

        $changed = array_slice($new, $start, count($new) - $start - $end);

        // Nothing added (only removed), or most of it new: plain is clearer.
        if ($changed === [] || count($changed) * 2 > count($new)) {
            return null;
        }

        return [
            implode('', array_slice($new, 0, $start)),
            implode('', $changed),
            implode('', array_slice($new, count($new) - $end)),
        ];
    }
}
