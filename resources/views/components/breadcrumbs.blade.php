{{--
    Breadcrumb trail — see App\Support\Breadcrumbs for how the resource
    crumb is derived automatically from the current route name.
--}}
@props(['root' => null, 'withResource' => true])
@php $crumbs = \App\Support\Breadcrumbs::trail($root, $withResource); @endphp
<nav aria-label="{{ __('Breadcrumb') }}" class="min-w-0 flex-1 truncate text-sm text-muted">
    <ol class="inline">
        @foreach ($crumbs as $index => $crumb)
            {{-- Root crumb and its separator are dropped on mobile to save space; separators are decoration. --}}
            <li @class(['inline' => $index > 0, 'hidden sm:inline' => $index === 0])>
                @if ($index === 1)
                    <span class="mx-2 hidden sm:inline" aria-hidden="true">/</span>
                @elseif ($index > 1)
                    <span class="mx-2" aria-hidden="true">/</span>
                @endif
                @if ($crumb['url'] && $index > 0)
                    <a href="{{ $crumb['url'] }}" class="hover:text-base-content">{{ $crumb['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
