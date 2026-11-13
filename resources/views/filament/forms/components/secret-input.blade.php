@php
    use Illuminate\View\ComponentAttributeBag;

    $statePath = $getStatePath();
    $id = $getId();
    $label = $getLabel();
    $noun = $getNoun();
    $isStored = $isStored();
    // A required secret can't be saved empty, so Remove would only end in an error.
    $canRemove = $isStored && ! $isRequired();
    // Filament's own links: helper text and error (aria-describedby), aria-invalid on error.
    $accessibilityAttributes = new ComponentAttributeBag($field->getAccessibilityAttributes());
@endphp

{{--
    A secret's field (App\Support\Filament\SecretInput). The stored value never
    reaches the page: a recessed row says whether one is saved, with its
    actions at the end (stacked under it on a phone). Three modes, owned by
    Alpine: kept (state null, so the field isn't submitted), edit (an empty
    input; saved as typed, empty removes it) and clear (an empty value, so
    saving removes it). A touched state is {value}, since Filament reads a
    blank string as null.
--}}
<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            mode: 'kept',
            draft: '',
            edit() {
                this.draft = ''
                this.state = { value: '' }
                this.mode = 'edit'
                this.focus('input')
            },
            clear() {
                this.state = { value: '' }
                this.mode = 'clear'
                this.focus('undo')
            },
            keep() {
                this.state = null
                this.mode = 'kept'
                this.focus('change')
            },
            // After the mode's block is shown: a hidden element can't take focus.
            focus(ref) {
                this.$nextTick(() => requestAnimationFrame(() => this.$refs[ref].focus()))
            },
        }"
        x-init="
            if (state !== null) { draft = state.value ?? ''; mode = 'edit' }
            $watch('draft', (value) => { if (mode === 'edit') { state = { value } } })
            // The form was filled again (after a save): back to saying what's stored.
            $watch('state', (value) => { if (value === null && mode !== 'kept') { mode = 'kept'; draft = '' } })
        "
    >
        <x-card inset body-class="gap-3 p-3 sm:flex-row sm:items-center sm:justify-between sm:px-4">
            {{-- Kept: what's saved, then Remove and Replace (or Add). --}}
            <span x-show="mode === 'kept'" id="{{ $id }}-status" class="text-sm {{ $isStored ? 'font-mono tracking-widest' : 'text-muted' }}">
                @if ($isStored)
                    <span aria-hidden="true">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                    <span class="sr-only">{{ __('admin.secret_saved', ['noun' => $noun]) }}</span>
                @else
                    {{ __('admin.secret_none', ['noun' => $noun]) }}
                @endif
            </span>

            <div x-show="mode === 'kept'" class="flex flex-wrap items-center gap-2 sm:justify-end">
                @if ($canRemove)
                    {{-- Danger family (it ends in a removed key), but quiet: nothing goes until Save, and Undo is beside it. --}}
                    <x-button
                        color="error"
                        variant="ghost"
                        size="sm"
                        x-on:click="clear()"
                        aria-describedby="{{ $id }}-status"
                        :aria-label="__('admin.secret_remove', ['noun' => $noun]).': '.$label"
                    >
                        {{ __('admin.secret_remove', ['noun' => $noun]) }}
                    </x-button>
                @endif

                <x-button
                    color="secondary"
                    variant="outline"
                    size="sm"
                    surface
                    x-ref="change"
                    x-on:click="edit()"
                    aria-describedby="{{ $id }}-status"
                    :aria-label="($isStored ? __('admin.secret_replace', ['noun' => $noun]) : __('admin.secret_add', ['noun' => $noun])).': '.$label"
                >
                    {{ $isStored ? __('admin.secret_replace', ['noun' => $noun]) : __('admin.secret_add', ['noun' => $noun]) }}
                </x-button>
            </div>

            {{-- Edit: an empty input; saved as typed. --}}
            <div x-show="mode === 'edit'" x-cloak class="min-w-0 sm:flex-1">
                <x-filament::input.wrapper :valid="! $errors->has($statePath)">
                    {{-- Filament's own input markup (x-filament::input), written out: a component tag can't spread these attributes. --}}
                    <input
                        type="password"
                        x-ref="input"
                        x-model="draft"
                        id="{{ $id }}"
                        autocomplete="new-password"
                        {{ $accessibilityAttributes->class(['fi-input']) }}
                    />
                </x-filament::input.wrapper>
            </div>

            <div x-show="mode === 'edit'" x-cloak class="flex sm:justify-end">
                <x-button.cancel size="sm" surface x-on:click="keep()">
                    {{ $isStored ? __('admin.secret_keep', ['noun' => $noun]) : __('admin.cancel') }}
                </x-button.cancel>
            </div>

            {{-- Clear: removed on save, until undone. --}}
            <span x-show="mode === 'clear'" x-cloak class="text-sm" role="status">{{ __('admin.secret_will_remove', ['noun' => $noun]) }}</span>

            <div x-show="mode === 'clear'" x-cloak class="flex sm:justify-end">
                <x-button.cancel size="sm" surface x-ref="undo" x-on:click="keep()">
                    {{ __('admin.secret_undo') }}
                </x-button.cancel>
            </div>
        </x-card>
    </div>
</x-dynamic-component>
