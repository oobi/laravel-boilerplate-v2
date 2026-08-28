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
        <li class="menu-title">{{ __('Manage Account') }}</li>
        <li><a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a></li>
        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left">{{ __('Log out') }}</button>
            </form>
        </li>

        <li class="menu-title">{{ __('Theme') }}</li>
        <li
            x-data="{
                mode: document.documentElement.dataset.themeMode ?? 'auto',
                setMode(mode) {
                    this.mode = mode;
                    document.cookie = `theme=${mode};path=/;max-age=31536000;samesite=lax`;
                    document.documentElement.dataset.themeMode = mode;
                    document.documentElement.setAttribute('data-theme', (mode === 'auto' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'boilerplate-dark' : 'boilerplate') : (mode === 'dark' ? 'boilerplate-dark' : 'boilerplate')));
                },
            }"
            x-init="window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (mode === 'auto') setMode('auto'); })"
        >
            <div class="join w-full">
                <button type="button" @click="setMode('light')" :class="mode === 'light' ? 'btn-soft btn-info' : 'btn-ghost'" class="btn btn-sm join-item flex-1" title="{{ __('Light') }}">
                    <x-heroicon-o-sun class="h-4 w-4" />
                </button>
                <button type="button" @click="setMode('dark')" :class="mode === 'dark' ? 'btn-soft btn-info' : 'btn-ghost'" class="btn btn-sm join-item flex-1" title="{{ __('Dark') }}">
                    <x-heroicon-o-moon class="h-4 w-4" />
                </button>
                <button type="button" @click="setMode('auto')" :class="mode === 'auto' ? 'btn-soft btn-info' : 'btn-ghost'" class="btn btn-sm join-item flex-1" title="{{ __('System') }}">
                    <x-heroicon-o-computer-desktop class="h-4 w-4" />
                </button>
            </div>
        </li>
    </ul>
</div>
