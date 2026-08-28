<div>
    @section('page-title', __('Dashboard'))

    <x-page-header title="{{ __('System Overview') }}" description="{{ __('Manage users and monitor platform activity') }}" />

    {{-- Stats grid — first card uses real data, the rest are disposable placeholders demonstrating the style. --}}
    <div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <x-stats-card label="{{ __('Total Users') }}" :value="number_format($totalUsers)" color="info" footer-color="success">
            <x-slot:icon>
                <x-heroicon-o-user-group class="h-8 w-8" />
            </x-slot:icon>
            <x-slot:footer>{{ __('Live data') }}</x-slot:footer>
        </x-stats-card>

        <x-stats-card label="{{ __('Card Title') }}" value="42" color="success" footer-color="neutral">
            <x-slot:icon>
                <x-heroicon-o-chart-bar class="h-8 w-8" />
            </x-slot:icon>
            <x-slot:footer>{{ __('Card content here') }}</x-slot:footer>
        </x-stats-card>

        <x-stats-card label="{{ __('Card Title') }}" value="128" color="accent" footer-color="neutral">
            <x-slot:icon>
                <x-heroicon-o-document-text class="h-8 w-8" />
            </x-slot:icon>
            <x-slot:footer>{{ __('Card content here') }}</x-slot:footer>
        </x-stats-card>

        <x-stats-card label="{{ __('Card Title') }}" value="7" color="warning" footer-color="neutral">
            <x-slot:icon>
                <x-heroicon-o-envelope class="h-8 w-8" />
            </x-slot:icon>
            <x-slot:footer>{{ __('Card content here') }}</x-slot:footer>
        </x-stats-card>
    </div>

    {{-- A few different card styles, mirroring the variety on Buzz's dashboard (disposable placeholder data beyond the first). --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Avatar-list card — real data --}}
        <div class="card bg-base-100">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <h3 class="card-title">{{ __('Recent Users') }}</h3>
                    <a href="{{ route('users.index') }}" class="ui-link text-sm">{{ __('View all') }}</a>
                </div>
                <div class="mt-2 flex flex-col gap-3">
                    @forelse ($recentUsers as $user)
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$user" size="sm" color="info"/>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">{{ $user->name }}</div>
                                <div class="truncate text-xs text-base-content/60">{{ $user->email }}</div>
                            </div>
                            @if ($user->system_role)
                                <x-badge :value="$user->system_role" />
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-base-content/60">{{ __('No recent users') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Plain list card — placeholder --}}
        <div class="card bg-base-100">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <h3 class="card-title">{{ __('Card Title') }}</h3>
                    <a href="#" class="ui-link text-sm">{{ __('View all') }}</a>
                </div>
                <div class="mt-2 flex flex-col gap-3">
                    @foreach (range(1, 4) as $i)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm">{{ __('Card content here') }} {{ $i }}</span>
                            <x-badge>{{ __('Item') }}</x-badge>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Number stat card — placeholder --}}
        <div class="card bg-base-100">
            <div class="card-body">
                <h3 class="card-title">{{ __('Card Title') }}</h3>
                <div class="mt-2 flex flex-col gap-3">
                    @foreach (['Metric A' => '12', 'Metric B' => '87%', 'Metric C' => '3.4k'] as $metricLabel => $metricValue)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-base-content/60">{{ $metricLabel }}</span>
                            <span class="font-semibold">{{ $metricValue }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card bg-base-100 lg:col-span-2">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <h3 class="card-title">{{ __('System Activity') }}</h3>
                    <a href="#" class="ui-link text-sm">{{ __('View all') }}</a>
                </div>
                <p class="ui-subtle mt-2">{{ __('Recent changes will appear here.') }}</p>
            </div>
        </div>

        <div class="card bg-base-100">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <h3 class="card-title">{{ __('Content Calendar') }}</h3>
                    <a href="#" class="ui-link text-sm">{{ __('View all') }}</a>
                </div>
                <p class="ui-subtle mt-2">{{ __('Upcoming dates will appear here.') }}</p>
            </div>
        </div>
    </div>
</div>
