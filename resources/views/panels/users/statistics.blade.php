{{-- Statistics panel (App\Panels\Users\StatisticsPanel) --}}
<x-card :title="__('admin.statistics')" type="panel">
    <x-stats-list>
        <x-stat-row
            icon="heroicon-o-calendar-days"
            :label="__('admin.account_age')"
            :value="$user->created_at?->diffForHumans()"
        />

        <x-stat-row
            icon="heroicon-o-clock"
            :label="__('admin.last_login')"
            :value="$user->last_login_at?->diffForHumans() ?? __('admin.never')"
            :muted="! $user->last_login_at"
        />
    </x-stats-list>
</x-card>
