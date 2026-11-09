<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Audit;

use App\Enums\SystemPermission;
use App\Models\AuditEntry;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditDisplay;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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

    public AuditEntry $entry;

    public function mount(AuditEntry $entry): void
    {
        Gate::authorize(SystemPermission::VIEW_AUDIT_LOG->value);

        $this->entry = $entry;
    }

    private function eventCode(): ?string
    {
        $event = $this->entry->event;

        return $event === null || strcasecmp(Audit::describe($event), $event) === 0 ? null : $event;
    }

    public function entryInfolist(Schema $schema): Schema
    {
        $subject = AuditDisplay::subject($this->entry);

        return $schema
            ->record($this->entry)
            ->components([
                Section::make(__('audit.details'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('description')
                                ->label(__('audit.what'))
                                ->extraAttributes(['class' => 'wrap-anywhere'])
                                ->columnSpanFull(),
                            TextEntry::make('created_at')->label(__('audit.when'))->dateTime(),
                            // In words, as the filter reads, with the code a developer searches for beneath
                            // when the two differ by more than a capital.
                            TextEntry::make('event')
                                ->label(__('audit.event'))
                                ->formatStateUsing(fn (string $state): string => Audit::describe($state))
                                ->helperText($this->eventCode())
                                ->placeholder(__('audit.none')),
                            TextEntry::make('log_name')
                                ->label(__('audit.category'))
                                ->formatStateUsing(fn (string $state): string => AuditDisplay::categoryLabel($state)),
                            TextEntry::make('subject_label')
                                ->label(__('audit.subject'))
                                ->state($subject['label'] ?? null)
                                ->url($subject['url'] ?? null)
                                ->placeholder(__('audit.none')),
                        ]),
                    ]),

                Section::make(__('audit.who_and_where'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('actor_name')->label(__('audit.actor'))->placeholder(__('audit.none')),
                            TextEntry::make('actor_type')
                                ->label(__('audit.actor_type'))
                                ->formatStateUsing(fn (string $state): string => AuditDisplay::actorLabel($state)),
                            TextEntry::make('impersonator_name')
                                ->label(__('audit.impersonated_by'))
                                ->placeholder(__('audit.none')),
                            TextEntry::make('ip_address')->label(__('audit.ip_address'))->placeholder(__('audit.none')),
                            TextEntry::make('user_agent')
                                ->label(__('audit.user_agent'))
                                ->placeholder(__('audit.none'))
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    /**
     * Each changed attribute, before and after. A created record has no "before",
     * and a deleted one no "after".
     *
     * @return Collection<int, array{field: string, before: string, after: string}>
     */
    public function changes(): Collection
    {
        $changes = collect($this->entry->attribute_changes ?? []);
        $before = collect($changes->get('old', []));
        $after = collect($changes->get('attributes', []));

        return $before->keys()->merge($after->keys())->unique()->sort()->values()
            ->map(fn (string $field): array => [
                'field' => $field,
                'before' => AuditDisplay::value($before->get($field)),
                'after' => AuditDisplay::value($after->get($field)),
            ]);
    }

    /**
     * Whatever else the entry recorded.
     *
     * @return Collection<int, array{name: string, value: string}>
     */
    public function properties(): Collection
    {
        return collect($this->entry->properties ?? [])
            ->map(fn (mixed $value, int|string $name): array => ['name' => (string) $name, 'value' => AuditDisplay::value($value)])
            ->values();
    }

    public function render(): View
    {
        return view('livewire.admin.audit.show-audit-entry');
    }
}
