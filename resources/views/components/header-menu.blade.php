{{--
    Header user menu — plain circular avatar trigger (no name text, no chevron),
    matching Buzz's header-menu look. daisyUI's dropdown styling, opened as a
    disclosure: a named button that says whether it's open, opens on click (not
    on focus), and closes on Escape (focus back to the button), a click outside or
    Tabbing out.
--}}
<div class="dropdown dropdown-end" x-data="{ open: false }" x-bind:class="{ 'dropdown-open': open }"
    x-on:keydown.escape="if (open) { $event.stopPropagation(); open = false; $refs.trigger.focus() }" x-on:click.outside="open = false"
    x-on:focusout="if (open && $event.relatedTarget && ! $el.contains($event.relatedTarget)) open = false">
    <button type="button" x-ref="trigger" class="btn btn-ghost btn-circle" aria-label="{{ __('Account menu') }}" aria-controls="account-menu"
        x-bind:aria-expanded="open.toString()" aria-expanded="false" x-on:click="open = ! open">
        <x-avatar color="primary" :user="auth()->user()" size="md" aria-hidden="true" />
    </button>
    <ul id="account-menu" class="dropdown-content menu z-[60] mt-2 w-52 rounded-box bg-base-100 p-2 shadow" x-show="open" x-cloak>
        {{-- Impersonated sessions can't open the profile (DenyLowAssuranceSessions). --}}
        @php($showProfile = ! \App\Support\Auth\SessionAssurance::isLow())
        @if ($showProfile)
            <li><a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a></li>
        @endif

        {{-- Cross-area / add-on links (e.g. the teams tier) — their own group, divided from account
             management above and functionally distinct from any same-named sidebar item. See
             App\Support\AccountMenu\AccountMenuRegistry. --}}
        @php($accountMenuLinks = \App\Support\AccountMenu\AccountMenuRegistry::visible(auth()->user()))
        @if ($accountMenuLinks->isNotEmpty())
            {{-- An <hr> inside the li, not an empty li — daisyUI collapses an empty menu li, so the divider needs content.
                 p-0 strips daisyUI's item padding (its :where() rules are zero-specificity) so the rule isn't offset. --}}
            @if ($showProfile)
                <li aria-hidden="true" class="pointer-events-none"><hr class="mx-3 my-1 border-base-200 p-0"></li>
            @endif
            @foreach ($accountMenuLinks as $item)
                <li>
                    <a href="{{ $item->getUrl() }}">{{ $item->getLabel() }}</a>
                </li>
            @endforeach
        @endif
        <li class="my-1 border-y border-base-200 py-2" x-data="{
            mode: document.documentElement.dataset.themeMode ?? 'auto',
            setMode(mode) {
                this.mode = mode;
                document.cookie = `theme=${mode};path=/;max-age=31536000;samesite=lax`;
                document.documentElement.dataset.themeMode = mode;
                document.documentElement.setAttribute('data-theme', (mode === 'auto' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'boilerplate-dark' : 'boilerplate') : (mode === 'dark' ? 'boilerplate-dark' : 'boilerplate')));
            },
        }" x-init="window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (mode === 'auto') setMode('auto'); })">
            <x-button-group :label="__('Theme')" block quiet size="sm">
                @foreach (['light' => ['heroicon-o-sun', __('Light')], 'dark' => ['heroicon-o-moon', __('Dark')], 'auto' => ['heroicon-o-computer-desktop', __('System')]] as $mode => [$icon, $label])
                    <x-button-group.item color="neutral" :title="$label" :aria-label="$label"
                        x-on:click="setMode('{{ $mode }}')"
                        x-bind:class="{ 'ui-button-group-btn-active-neutral': mode === '{{ $mode }}' }"
                        x-bind:aria-pressed="mode === '{{ $mode }}'">
                        <x-dynamic-component :component="$icon" class="h-4 w-4" />
                    </x-button-group.item>
                @endforeach
            </x-button-group>
        </li>

        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 text-left">
                    <x-heroicon-o-arrow-right-on-rectangle class="h-4 w-4" />
                    {{ __('Log out') }}
                </button>
            </form>
        </li>
    </ul>
</div>
