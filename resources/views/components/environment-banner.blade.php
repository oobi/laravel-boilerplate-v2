{{--
    Fixed top strip naming a non-production environment ("STAGING - not live
    data"), from ENVIRONMENT_BANNER_TEXT / _COLOR via App\Support\EnvironmentBanner.
    Renders nothing while the text is unset. The layout adds the
    has-environment-banner body class, which reserves its height. A note, not
    an alert or status, so screen readers don't announce it on every page.
--}}
@if (\App\Support\EnvironmentBanner::isShown())
    <div
        class="ui-environment-banner"
        role="note"
        style="background-color: {{ \App\Support\EnvironmentBanner::color() }}; color: {{ \App\Support\EnvironmentBanner::contentColor() }};"
    >
        <span class="truncate">{{ \App\Support\EnvironmentBanner::text() }}</span>
    </div>
@endif
