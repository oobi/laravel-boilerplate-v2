<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\Tables;

use App\Enums\SystemPermission;
use Concise\ThemeDemo\Enums\DemoStatus;
use Concise\ThemeDemo\Support\DemoRow;
use Concise\ThemeDemo\Support\DemoRows;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class MaximalistTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $sortColumn = 'name';

    public string $sortDirection = 'asc';

    /** @var list<int> */
    public array $selected = [];

    /** @var list<int> */
    public array $expanded = [];

    public int $perPage = 10;

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleExpand(int $id): void
    {
        $this->expanded = in_array($id, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$id]))
            : [...$this->expanded, $id];
    }

    public function toggleSelectAll(): void
    {
        $ids = $this->filtered()->pluck('id')->all();

        $this->selected = count(array_intersect($ids, $this->selected)) === count($ids)
            ? []
            : $ids;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /** Demo-only bulk action — proves the selection UI wires up to something, does not persist anywhere. */
    public function bulkArchive(): void
    {
        session()->flash('status', __('Archived :count demo row(s).', ['count' => count($this->selected)]));

        $this->selected = [];
    }

    /** @return array<string, string> */
    #[Computed]
    public function statusOptions(): array
    {
        return DemoStatus::options();
    }

    /** @return Collection<int, DemoRow> */
    protected function filtered(): Collection
    {
        return DemoRows::all()
            ->when($this->search !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (DemoRow $row): bool => str_contains(mb_strtolower($row->name), mb_strtolower($this->search))
            ))
            ->when($this->statusFilter !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (DemoRow $row): bool => $row->status->value === $this->statusFilter
            ))
            ->sortBy(
                fn (DemoRow $row): string => $row->{$this->sortColumn} instanceof DemoStatus
                    ? $row->{$this->sortColumn}->value
                    : (string) $row->{$this->sortColumn},
                SORT_REGULAR,
                $this->sortDirection === 'desc'
            )
            ->values();
    }

    /** @return LengthAwarePaginatorContract<int, DemoRow> */
    #[Computed]
    public function rows(): LengthAwarePaginatorContract
    {
        $filtered = $this->filtered();
        $page = $this->getPage();

        return new LengthAwarePaginator(
            $filtered->forPage($page, $this->perPage)->values(),
            $filtered->count(),
            $this->perPage,
            $page,
        );
    }

    public function render(): View
    {
        return view('theme-demo::livewire.tables.maximalist')->layout('layouts.admin');
    }
}
