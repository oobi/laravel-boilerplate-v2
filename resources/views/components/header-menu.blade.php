{{--
    Header user menu — plain circular avatar trigger (no name text, no chevron),
    matching Buzz's header-menu look. Uses daisyUI's native dropdown instead of
    a custom dropdown component.
--}}
<div class="dropdown dropdown-end">
    <div tabindex="0" role="button" class="btn btn-ghost btn-circle">
        <x-avatar color="primary" :user="auth()->user()" size="md" />
    </div>
    <ul tabindex="0" class="dropdown-content menu z-[60] mt-2 w-52 rounded-box bg-base-100 p-2 shadow">
        <li><a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a></li>

        {{-- Cross-area / add-on links (e.g. the teams tier) — their own group, divided from account
             management above and functionally distinct from any same-named sidebar item. See
             App\Support\AccountMenu\AccountMenuRegistry. --}}
        @php($accountMenuLinks = \App\Support\AccountMenu\AccountMenuRegistry::visible(auth()->user()))
        @if ($accountMenuLinks->isNotEmpty())
            {{-- An <hr> inside the li, not an empty li — daisyUI collapses an empty menu li, so the divider needs content.
                 p-0 strips daisyUI's item padding (its :where() rules are zero-specificity) so the rule isn't offset. --}}
            <li aria-hidden="true" class="pointer-events-none"><hr class="mx-3 my-1 border-base-200 p-0"></li>
            @foreach ($accountMenuLinks as $item)
                <li>
                    <a href="{{ $item->getUrl() }}" class="flex items-center gap-2">
                        @if ($item->getIcon())
                            <x-dynamic-component :component="$item->getIcon()" class="h-4 w-4 shrink-0" />
                        @endif
                        {{ $item->getLabel() }}
                    </a>
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
            <div class="join w-full bg-transparent p-0 hover:bg-transparent">
                <button type="button" @click="setMode('light')"
                    :class="mode === 'light' ? 'bg-base-200 text-base-content' : 'text-base-content/50 hover:bg-base-200/70 hover:text-base-content'"
                    class="btn btn-ghost btn-sm join-item flex-1"
                    title="{{ __('Light') }}">
                    <x-heroicon-o-sun class="h-4 w-4" />
                </button>
                <button type="button" @click="setMode('dark')"
                    :class="mode === 'dark' ? 'bg-base-200 text-base-content' : 'text-base-content/50 hover:bg-base-200/70 hover:text-base-content'"
                    class="btn btn-ghost btn-sm join-item flex-1"
                    title="{{ __('Dark') }}">
                    <x-heroicon-o-moon class="h-4 w-4" />
                </button>
                <button type="button" @click="setMode('auto')"
                    :class="mode === 'auto' ? 'bg-base-200 text-base-content' : 'text-base-content/50 hover:bg-base-200/70 hover:text-base-content'"
                    class="btn btn-ghost btn-sm join-item flex-1"
                    title="{{ __('System') }}">
                    <x-heroicon-o-computer-desktop class="h-4 w-4" />
                </button>
            </div>
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
