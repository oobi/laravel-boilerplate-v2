{{--
    Header user menu — plain circular avatar trigger (no name text, no chevron),
    matching Buzz's header-menu look. Uses daisyUI's native dropdown instead of
    a custom dropdown component.
--}}
<div class="dropdown dropdown-end">
    <div tabindex="0" role="button" class="btn btn-ghost btn-circle">
        <x-avatar color="primary" :name="auth()->user()->name" size="md" />
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
    </ul>
</div>
