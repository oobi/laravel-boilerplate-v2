{{--
    Breadcrumb trail — see App\Support\Breadcrumbs for how the resource
    crumb is derived automatically from the current route name.
--}}
@php $crumbs = \App\Support\Breadcrumbs::trail(); @endphp
<div class="min-w-0 flex-1 text-sm text-base-content/60">
    @foreach ($crumbs as $index => $crumb)
        @if ($index > 0)
            <span class="mx-2">/</span>
        @endif
        @if ($crumb['url'])
            <a href="{{ $crumb['url'] }}" class="hover:text-base-content">{{ $crumb['label'] }}</a>
        @else
            <span>{{ $crumb['label'] }}</span>
        @endif
    @endforeach
</div>
