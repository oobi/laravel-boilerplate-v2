{{--
    Blocking script for the theme cookie's 'auto' mode — must run before
    first paint to avoid a flash of the wrong theme. Explicit light/dark
    modes are already baked into the server-rendered data-theme attribute
    (see layouts/admin.blade.php + layouts/guest.blade.php); this only ever
    has to correct the auto case, which the server can't resolve since it
    doesn't know the OS preference.
--}}
<script>
    (function () {
        if (document.documentElement.dataset.themeMode !== 'auto') {
            return;
        }

        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'boilerplate-dark');
        }
    })();
</script>
