@section('page-title', __('theme-demo::messages.components_title'))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('theme-demo::messages.components_title')" :description="__('theme-demo::messages.components_description')" />

    {{-- Alerts (flash-style messages) --}}
    <x-card :title="__('Alerts')">
        <x-alert color="success">{{ __('Success message.') }}</x-alert>
        <x-alert color="error">{{ __('Error message.') }}</x-alert>
        <x-alert color="warning">{{ __('Warning message.') }}</x-alert>
        <x-alert color="info">{{ __('Info message.') }}</x-alert>
    </x-card>

    {{-- Banners --}}
    <x-card :title="__('Banners')">
        @foreach (['info', 'success', 'warning', 'error'] as $variant)
            <x-banner :variant="$variant" dismissible>
                {{ ucfirst($variant) }} banner — persistent, contextual messaging.
            </x-banner>
        @endforeach
    </x-card>

    {{-- Cards — including nested (inset) cards --}}
    <x-card :title="__('Cards')">
        <p class="ui-subtle text-sm">{{ __('A card nested inside another card/panel should use `inset` so it reads as a recessed well (no shadow, shaded to base-200) rather than a second floating island.') }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-card :title="__('Default')" type="panel">
                <p class="text-sm">{{ __('Standard island — shadow + base-100 surface.') }}</p>
            </x-card>

            <x-card :title="__('Inset')" type="panel" inset>
                <p class="text-sm">{{ __('Recessed into the parent — flat + base-200 surface.') }}</p>
            </x-card>
        </div>

        {{-- Accent: a status edge; the text stays in the body colour, so it stays readable. --}}
        <p class="ui-subtle text-sm">{{ __('`accent` takes any of the 8 semantic colours and adds a thick top edge to mark status (needs attention, failed, done) without tinting the text. `footer` adds an action row under a divider.') }}</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['info', 'success', 'warning', 'error'] as $accentColor)
                <x-card :title="ucfirst($accentColor)" type="panel" :accent="$accentColor">
                    <p class="text-sm"><code>accent="{{ $accentColor }}"</code></p>
                </x-card>
            @endforeach
        </div>

        {{-- A needs-attention card: accent, an icon tile, inset items and a footer of actions. --}}
        <x-card accent="warning" bodyClass="gap-6">
            <div class="flex items-start gap-4">
                <x-avatar icon="heroicon-o-exclamation-triangle" color="warning" square class="shrink-0" />
                <div class="flex flex-col gap-1">
                    <h3 class="text-lg font-semibold">{{ __('2 invoices need attention') }}</h3>
                    <p class="text-base-content/70">{{ __('These were returned by the payment provider. Check the details, then retry or cancel them.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-4">
                @foreach ([['INV-1042', 'Card declined'], ['INV-1047', 'Expired card']] as [$invoice, $reason])
                    <x-card :title="$invoice" type="panel" inset>
                        <p class="text-sm text-base-content/70">{{ $reason }}</p>
                    </x-card>
                @endforeach
            </div>

            <x-slot:footer>
                <x-button.action>
                    {{ __('Review invoices') }}
                    <x-heroicon-o-arrow-right class="size-4" />
                </x-button.action>
                <x-button color="neutral" variant="ghost">{{ __('Dismiss') }}</x-button>
            </x-slot:footer>
        </x-card>

        {{-- Sticky footer: a long card keeps its action row in view while it scrolls past. --}}
        <p class="ui-subtle text-sm">{{ __('`sticky-footer` keeps the footer at the bottom of the screen until the card\'s end scrolls into view, so a confirm button below a long list stays in reach. Scroll past this card to see it.') }}</p>

        <x-card :title="__('Review 24 items')" type="panel" sticky-footer>
            <ul class="divide-y divide-base-300 text-sm">
                @foreach (range(1, 24) as $item)
                    <li class="flex justify-between py-2">
                        <span>{{ __('Item :number', ['number' => $item]) }}</span>
                        <span class="ui-subtle">{{ __('Ready') }}</span>
                    </li>
                @endforeach
            </ul>

            <x-slot:footer>
                <span class="ui-subtle text-sm">{{ __('Nothing is saved until you confirm.') }}</span>
                <div class="ml-auto flex gap-2">
                    <x-button.cancel />
                    <x-button.action>{{ __('Confirm') }}</x-button.action>
                </div>
            </x-slot:footer>
        </x-card>
    </x-card>

    {{-- Buttons --}}
    <x-card :title="__('Buttons')" bodyClass="gap-4">
        {{-- Semantic intent components — the preferred API in app markup (see .ai/rules/components.md) --}}
        <div>
            <div class="ui-subtle mb-1">{{ __('Semantic') }}</div>
            <p class="ui-subtle mb-2 text-sm">{{ __('theme-demo::messages.components_buttons_semantic_hint') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <x-button.action>{{ __('Action') }}</x-button.action>
                <x-button.secondary>{{ __('Secondary') }}</x-button.secondary>
                <x-button.cancel />
                <x-button.back href="#" />
                <x-button.warning>{{ __('Warning') }}</x-button.warning>
                <x-button.danger>{{ __('Danger') }}</x-button.danger>
                <x-button.icon aria-label="{{ __('Open menu') }}">
                    <x-heroicon-o-bars-3 class="h-5 w-5" />
                </x-button.icon>
                <x-button.icon circle aria-label="{{ __('admin.close') }}">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </x-button.icon>
            </div>
        </div>

        {{-- Primitive: raw colour × variant matrix behind the semantic components --}}
        <div class="ui-subtle mb-1 border-t border-base-300 pt-4">{{ __('Primitive') }} (&lt;x-button&gt;)</div>
        @foreach ($buttonVariants as $variant)
            <div>
                <div class="ui-subtle mb-1">{{ ucfirst($variant) }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($colors as $color)
                        <x-button :color="$color" :variant="$variant" size="sm">{{ ucfirst($color) }}</x-button>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Surface: outline buttons on a tinted panel, transparent vs filled at rest. --}}
        <div>
            <div class="ui-subtle mb-1">{{ __('Surface') }}</div>
            <p class="ui-subtle mb-2 text-sm">{{ __('On a tinted panel such as an inset card, an outline button takes the panel\'s colour. `surface` fills it with base-100 at rest; hover and press look the same as ever.') }}</p>
            <x-card inset>
                <div class="flex flex-wrap items-center gap-2">
                    <x-button color="neutral" variant="outline">{{ __('Outline') }}</x-button>
                    <x-button color="neutral" variant="outline" surface>{{ __('Outline + surface') }}</x-button>
                    <x-button color="primary" variant="outline" surface>{{ __('Primary + surface') }}</x-button>
                </div>
            </x-card>
        </div>

        {{-- Button group: the one segmented control (never daisyUI join). In a Filament form, ToggleButtons::grouped() is drawn to match. --}}
        <div class="flex flex-col gap-3">
            <div class="ui-subtle">{{ __('Button group') }} (&lt;x-button-group&gt;)</div>
            <div class="flex flex-wrap items-center gap-4">
                <x-button-group :label="__('Status')">
                    <x-button-group.item active>{{ __('All') }}</x-button-group.item>
                    <x-button-group.item>{{ __('Active') }}</x-button-group.item>
                    <x-button-group.item>{{ __('Archived') }}</x-button-group.item>
                </x-button-group>

                {{-- With counts, as the table toolbar's active/trashed toggle. --}}
                <x-button-group :label="__('Records shown')">
                    <x-button-group.item active>
                        <x-heroicon-m-check class="h-4 w-4" />
                        <x-badge color="info" size="xs">156</x-badge>
                    </x-button-group.item>
                    <x-button-group.item color="danger">
                        <x-heroicon-o-trash class="h-4 w-4" />
                        <x-badge color="error" size="xs">1</x-badge>
                    </x-button-group.item>
                </x-button-group>
            </div>

            {{-- Quiet, full width and small, as the theme switcher in the user menu. --}}
            <div class="max-w-xs">
                <x-button-group :label="__('Theme')" block quiet size="sm">
                    <x-button-group.item color="neutral" :aria-label="__('Light')"><x-heroicon-o-sun class="h-4 w-4" /></x-button-group.item>
                    <x-button-group.item color="neutral" active :aria-label="__('Dark')"><x-heroicon-o-moon class="h-4 w-4" /></x-button-group.item>
                    <x-button-group.item color="neutral" :aria-label="__('System')"><x-heroicon-o-computer-desktop class="h-4 w-4" /></x-button-group.item>
                </x-button-group>
            </div>
        </div>

        <div>
            <div class="ui-subtle mb-1">{{ __('Sizes') }}</div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($sizes as $size)
                    <x-button color="primary" :size="$size">{{ strtoupper($size) }}</x-button>
                @endforeach
            </div>
        </div>
    </x-card>

    {{-- Badges --}}
    <x-card :title="__('Badges')">
        @foreach (['solid', 'soft', 'outline', 'ghost'] as $variant)
            <div>
                <div class="ui-subtle mb-1">{{ ucfirst($variant) }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($colors as $color)
                        <x-badge :color="$color" :variant="$variant">{{ ucfirst($color) }}</x-badge>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div>
            <div class="ui-subtle mb-1">{{ __('Sizes') }}</div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($sizes as $size)
                    <x-badge color="primary" :size="$size">{{ strtoupper($size) }}</x-badge>
                @endforeach
            </div>
        </div>
    </x-card>

    {{-- Avatars --}}
    <x-card :title="__('Avatars')">
        @foreach (['solid', 'soft', 'ghost', 'outline'] as $variant)
            <div>
                <div class="ui-subtle mb-1">{{ ucfirst($variant) }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($colors as $color)
                        <x-avatar name="{{ ucfirst($color) }} Demo" :color="$color" :variant="$variant" />
                    @endforeach
                </div>
            </div>
        @endforeach

        <div>
            <div class="ui-subtle mb-1">{{ __('Sizes') }}</div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size)
                    <x-avatar name="Jane Doe" :size="$size" />
                @endforeach
            </div>
        </div>

        {{-- Icon: an icon tile for a card or banner header, in the same colours, variants and sizes. --}}
        <div>
            <div class="ui-subtle mb-1">{{ __('Icon') }} (<code>icon="heroicon-o-…" square</code>)</div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['info' => 'heroicon-o-information-circle', 'success' => 'heroicon-o-check-circle', 'warning' => 'heroicon-o-exclamation-triangle', 'error' => 'heroicon-o-x-circle'] as $color => $icon)
                    <x-avatar :icon="$icon" :color="$color" square />
                @endforeach
                @foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size)
                    <x-avatar icon="heroicon-o-users" color="primary" :size="$size" square />
                @endforeach
            </div>
        </div>
    </x-card>

    {{-- Stats cards --}}
    <x-card :title="__('Stats cards')">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stats-card label="Total users" value="1,204" color="primary" />
            <x-stats-card label="Active today" value="87" color="success" />
            <x-stats-card label="Pending review" value="12" color="warning" />
        </div>
    </x-card>

    {{-- Pickers: <x-date-picker> and <x-listbox>, each with the value it sets --}}
    <x-card :title="__('Date pickers')">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['period', __('Range with presets'), ['mode' => 'range', 'presets' => ['today' => __('Today'), 'upcoming' => __('Upcoming'), 'all' => __('All dates')]]],
                ['stay', __('Range only'), ['mode' => 'range', 'clearable' => true]],
                ['appointment', __('Single with presets'), ['mode' => 'single', 'presets' => ['' => __('Any date'), 'today' => __('Today')]]],
                ['dueDate', __('Single from today'), ['mode' => 'single', 'min' => now()->toDateString(), 'clearable' => true]],
            ] as [$property, $title, $props])
                <div class="flex flex-col gap-2" wire:key="date-picker-demo-{{ $property }}">
                    <x-date-picker
                        :id="'date-picker-demo-'.$property"
                        :model="$property"
                        :label="$title"
                        :mode="$props['mode']"
                        :presets="$props['presets'] ?? []"
                        :clearable="$props['clearable'] ?? false"
                        :min="$props['min'] ?? null"
                        class="w-full"
                    />
                    <div class="ui-subtle text-xs">{{ __('Value') }}: <code>{{ $this->{$property} === '' ? __('(empty)') : $this->{$property} }}</code></div>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card :title="__('Listbox')">
        <div class="flex max-w-xs flex-col gap-2">
            <x-listbox
                id="listbox-demo"
                model="priority"
                :label="__('Priority')"
                :placeholder="__('Any priority')"
                :options="['high' => __('High'), 'medium' => __('Medium'), 'low' => __('Low'), 'none' => __('No dot')]"
                :dots="['high' => 'bg-error', 'medium' => 'bg-warning', 'low' => 'bg-success']"
                class="w-full"
            />
            <div class="ui-subtle text-xs">{{ __('Value') }}: <code>{{ $priority === '' ? __('(empty)') : $priority }}</code></div>
        </div>
    </x-card>

    {{-- Color matrix — this app has no shade-ramp utilities (see theme/components/ui/colors.css), just solid + soft per semantic color --}}
    <x-card :title="__('Color palette')">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($colors as $color)
                <div class="flex flex-col gap-1">
                    <div class="ui-subtle">{{ ucfirst($color) }}</div>
                    <div class="flex h-10 items-center justify-center rounded-box bg-{{ $color }} text-{{ $color }}-content text-xs">{{ __('solid') }}</div>
                    <div class="flex h-10 items-center justify-center rounded-box bg-soft-{{ $color }} text-{{ $color }} text-xs">{{ __('soft') }}</div>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
