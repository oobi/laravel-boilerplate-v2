{{-- Statistics panel (App\Panels\Users\StatisticsPanel) --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-stats-card label="{{ __('admin.account_age') }}" value="{{ $user->created_at?->diffForHumans() }}" color="info" />

    <x-stats-card label="{{ __('admin.last_login') }}"
        value="{{ $user->last_login_at?->diffForHumans() ?? __('admin.never') }}" color="info" />
</div>
