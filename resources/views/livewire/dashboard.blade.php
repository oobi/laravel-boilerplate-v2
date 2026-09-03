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
        <x-card :title="__('Recent Users')">
            <x-slot:actions>
                <a href="{{ route('users.index') }}" class="ui-link text-sm">{{ __('View all') }}</a>
            </x-slot:actions>

            <div class="flex flex-col gap-3">
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
        </x-card>

        {{-- Plain list card — placeholder --}}
        <x-card :title="__('Card Title')">
            <x-slot:actions>
                <a href="#" class="ui-link text-sm">{{ __('View all') }}</a>
            </x-slot:actions>

            <div class="flex flex-col gap-3">
                @foreach (range(1, 4) as $i)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm">{{ __('Card content here') }} {{ $i }}</span>
                        <x-badge>{{ __('Item') }}</x-badge>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Number stat card — placeholder --}}
        <x-card :title="__('Card Title')">
            <div class="flex flex-col gap-3">
                @foreach (['Metric A' => '12', 'Metric B' => '87%', 'Metric C' => '3.4k'] as $metricLabel => $metricValue)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-base-content/60">{{ $metricLabel }}</span>
                        <span class="font-semibold">{{ $metricValue }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card :title="__('System Activity')"  class="lg:col-span-2">
            <x-slot:actions>
                <a href="#" class="ui-link text-sm">{{ __('View all') }}</a>
            </x-slot:actions>

            <p class="ui-subtle">{{ __('Recent changes will appear here.') }}</p>
        </x-card>

        <x-card :title="__('Content Calendar')">
            <x-slot:actions>
                <a href="#" class="ui-link text-sm">{{ __('View all') }}</a>
            </x-slot:actions>

            <p class="ui-subtle">{{ __('Upcoming dates will appear here.') }}</p>
        </x-card>
    </div>
</div>
