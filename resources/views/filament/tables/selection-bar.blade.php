{{--
    Rendered into a Filament table's selection indicator by the render hook in
    AppServiceProvider, so it sits in the table's own Alpine scope.

    - The count, named for the rows ("2 users selected"), at every width;
      filament-table.css hides Filament's own "records" count.
    - Below lg, a "Bulk actions" button that opens the table's bulk actions in
      a bottom sheet: a native <dialog> (Escape, focus return and an inert page
      come free) with Filament's own actions drawn as full-width buttons in
      their colours (a clone of each, so the header's menu keeps its rows), so
      confirmations, authorisation and per-action visibility are Filament's. Not <x-modal>:
      that shell needs a wire:model property, and this lives in Filament's
      table view, not a component of ours. From lg up Filament's own dropdown
      is used instead.
--}}
@php
    $one = trans_choice('admin.records_selected', 1, ['count' => '__COUNT__', 'label' => $table->getModelLabel()]);
    $many = trans_choice('admin.records_selected', 2, ['count' => '__COUNT__', 'label' => $table->getPluralModelLabel()]);
    $countText = '(getSelectedRecordsCount() === 1 ? '.\Illuminate\Support\Js::from($one).' : '.\Illuminate\Support\Js::from($many).').replace(\'__COUNT__\', new Intl.NumberFormat('.\Illuminate\Support\Js::from(str_replace('_', '-', app()->getLocale())).').format(getSelectedRecordsCount()))';
    $headingId = $componentId.'-bulk-sheet-heading';
@endphp

<span class="ui-selection-count" x-text="{{ $countText }}"></span>

@if ($bulkActions !== [])
    <x-button class="ui-selection-bar-button" aria-haspopup="dialog" x-on:click="$refs.uiBulkSheet.showModal()">
        {{ $bulkLabel }}
        <x-heroicon-m-chevron-up class="size-4" aria-hidden="true" />
    </x-button>

    {{-- Closes itself if the selection empties under it (a refresh), so the page is never left inert behind an invisible sheet. --}}
    <dialog
        x-ref="uiBulkSheet"
        class="ui-bulk-sheet"
        aria-labelledby="{{ $headingId }}"
        x-effect="getSelectedRecordsCount() || $el.close()"
        x-on:click.self="$el.close()"
    >
        <div class="ui-bulk-sheet__body">
            <div class="ui-bulk-sheet__header">
                <h2 id="{{ $headingId }}" class="ui-bulk-sheet__heading" x-text="{{ $countText }}"></h2>
                <x-button.icon :aria-label="__('admin.close')" x-on:click="$refs.uiBulkSheet.close()">
                    <x-heroicon-o-x-mark class="size-5" aria-hidden="true" />
                </x-button.icon>
            </div>

            {{-- Choosing an action closes the sheet first, so Filament's confirmation opens over the page, not under it. --}}
            <div class="ui-bulk-sheet__actions" x-on:click="if ($event.target.closest('button')) $refs.uiBulkSheet.close()">
                @foreach ($bulkActions as $action)
                    {{ (clone $action)->button()->size(\Filament\Support\Enums\Size::Large) }}
                @endforeach
            </div>
        </div>
    </dialog>
@endif
