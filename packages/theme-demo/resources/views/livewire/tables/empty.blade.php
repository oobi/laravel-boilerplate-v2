@section('page-title', __('theme-demo::messages.tables_empty_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.tables_empty_title')" :description="__('theme-demo::messages.tables_empty_description')" />

    <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Joined') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="4">
                        <div class="flex flex-col items-center gap-2 py-12 text-center">
                            <x-heroicon-o-inbox class="h-10 w-10 text-base-content/30" />
                            <p class="font-medium">{{ __('theme-demo::messages.tables_empty_message') }}</p>
                            <p class="text-sm text-base-content/60">{{ __('theme-demo::messages.tables_empty_hint') }}</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
