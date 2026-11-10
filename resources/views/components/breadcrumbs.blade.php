{{--
    Breadcrumb trail: see App\Support\Breadcrumbs for how the resource
    crumb is derived automatically from the current route name.

    A row of items, not a line of text: each crumb is a list item standing on
    its own, like the app's other navigation links, so its colour is cue
    enough (as inline text, axe's link-in-text-block wanted an underline).
    A grid, not flex: its columns share the room out evenly, so a long crumb,
    usually a name, shortens with an ellipsis first and short ones (Users,
    Edit) stay whole unless the row is very tight. Flex would shorten every
    crumb a little.
--}}
@props(['root' => null, 'withResource' => true])
@php $crumbs = \App\Support\Breadcrumbs::trail($root, $withResource); @endphp
<nav aria-label="{{ __('Breadcrumb') }}" class="min-w-0 flex-1 text-sm text-muted">
    <ol class="grid auto-cols-[minmax(0,max-content)] grid-flow-col items-center">
        @foreach ($crumbs as $index => $crumb)
            {{-- Root crumb and its separator are dropped on mobile to save space; separators are decoration. --}}
            <li @class(['min-w-0 items-center', 'flex' => $index > 0, 'hidden sm:flex' => $index === 0])>
                @if ($index === 1)
                    <span class="mx-3 hidden sm:inline" aria-hidden="true">/</span>
                @elseif ($index > 1)
                    <span class="mx-3" aria-hidden="true">/</span>
                @endif
                {{-- Links look like the app's other links (ui-link), so they stand apart from plain crumbs and the current page. --}}
                @if ($crumb['url'])
                    <a href="{{ $crumb['url'] }}" class="ui-link min-w-0 truncate">{{ $crumb['label'] }}</a>
                @else
                    <span class="min-w-0 truncate" @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
