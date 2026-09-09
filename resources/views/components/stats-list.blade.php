{{--
    <x-stats-list> — a compact "spec sheet" of label/value stats, rendered as a
    semantic description list (`<dl>`). Fill it with `<x-stat-row>` children.

    Preferred over a grid of `<x-stats-card>` tiles when the labels matter as
    much as the values and the list is open-ended (a User's stats, an entity's
    metadata) — it stays readable in a narrow sidebar column and extending it is
    one more `<x-stat-row>`. Reserve `<x-stats-card>` for a dashboard KPI row
    where the headline number is the point.

    Usually placed inside a `<x-card type="panel">`.
--}}
<dl {{ $attributes->class('space-y-3') }}>
    {{ $slot }}
</dl>
