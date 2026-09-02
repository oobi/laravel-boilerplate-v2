@section('page-title', __('theme-demo::messages.tab_content_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.tab_content_title')" :description="__('theme-demo::messages.tab_content_description')" />

    <x-tabs-nav>
        @include('theme-demo::livewire.tab-content._tabs')

        <x-slot:content>
            @if ($variant === 'table')
                <div class="overflow-x-auto">
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
                            @foreach ($this->rows as $row)
                                <tr wire:key="tab-content-row-{{ $row->id }}">
                                    <td class="flex items-center gap-3">
                                        <x-avatar :name="$row->name" size="sm" />
                                        <span class="font-medium">{{ $row->name }}</span>
                                    </td>
                                    <td>{{ $row->email }}</td>
                                    <td><x-badge :value="$row->status" /></td>
                                    <td>{{ $row->joinedAt }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($variant === 'form')
                <form class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="ui-floating-label">
                        <input id="tab-content-name" type="text" value="{{ __('theme-demo::messages.tab_content_form_name_value') }}" placeholder=" " class="input w-full">
                        <label for="tab-content-name" class="ui-floating-label-text">{{ __('theme-demo::messages.tab_content_form_name') }}</label>
                    </div>

                    <div class="ui-floating-label">
                        <input id="tab-content-email" type="email" value="{{ __('theme-demo::messages.tab_content_form_email_value') }}" placeholder=" " class="input w-full">
                        <label for="tab-content-email" class="ui-floating-label-text">{{ __('theme-demo::messages.tab_content_form_email') }}</label>
                    </div>

                    <div class="ui-floating-label md:col-span-2">
                        <select id="tab-content-plan" class="select w-full">
                            <option>{{ __('theme-demo::messages.tab_content_form_plan_standard') }}</option>
                            <option>{{ __('theme-demo::messages.tab_content_form_plan_growth') }}</option>
                            <option>{{ __('theme-demo::messages.tab_content_form_plan_enterprise') }}</option>
                        </select>
                        <label for="tab-content-plan" class="ui-floating-label-text">{{ __('theme-demo::messages.tab_content_form_plan') }}</label>
                    </div>

                    <div class="ui-floating-label md:col-span-2">
                        <textarea id="tab-content-notes" placeholder=" " class="textarea w-full" rows="4">{{ __('theme-demo::messages.tab_content_form_notes_value') }}</textarea>
                        <label for="tab-content-notes" class="ui-floating-label-text">{{ __('theme-demo::messages.tab_content_form_notes') }}</label>
                    </div>

                    <label class="label justify-start gap-3 md:col-span-2">
                        <input type="checkbox" class="checkbox checkbox-sm" checked>
                        <span>{{ __('theme-demo::messages.tab_content_form_updates') }}</span>
                    </label>

                    <div class="flex justify-end md:col-span-2">
                        <button type="button" class="btn btn-primary">{{ __('theme-demo::messages.tab_content_form_save') }}</button>
                    </div>
                </form>
            @elseif ($variant === 'panels')
                <div class="flex flex-col gap-6">
                    <x-banner variant="info">
                        {{ __('theme-demo::messages.tab_content_panels_banner') }}
                    </x-banner>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <x-stats-card :label="__('theme-demo::messages.tab_content_panels_active_label')" :value="__('theme-demo::messages.tab_content_panels_active_value')" color="success">
                            <x-slot:icon><x-heroicon-o-user-group class="h-6 w-6" /></x-slot:icon>
                            <x-slot:footer>{{ __('theme-demo::messages.tab_content_panels_active_footer') }}</x-slot:footer>
                        </x-stats-card>

                        <x-stats-card :label="__('theme-demo::messages.tab_content_panels_response_label')" :value="__('theme-demo::messages.tab_content_panels_response_value')" color="info">
                            <x-slot:icon><x-heroicon-o-clock class="h-6 w-6" /></x-slot:icon>
                            <x-slot:footer>{{ __('theme-demo::messages.tab_content_panels_response_footer') }}</x-slot:footer>
                        </x-stats-card>

                        <x-stats-card :label="__('theme-demo::messages.tab_content_panels_tasks_label')" :value="__('theme-demo::messages.tab_content_panels_tasks_value')" color="warning">
                            <x-slot:icon><x-heroicon-o-check-circle class="h-6 w-6" /></x-slot:icon>
                            <x-slot:footer>{{ __('theme-demo::messages.tab_content_panels_tasks_footer') }}</x-slot:footer>
                        </x-stats-card>
                    </div>

                    <div class="border-t border-base-300 pt-6">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="ui-h3">{{ __('theme-demo::messages.tab_content_panels_activity_title') }}</h2>
                            <x-badge value="{{ __('theme-demo::messages.tab_content_panels_activity_badge') }}" color="success" />
                        </div>

                        <div class="mt-4 flex flex-col divide-y divide-base-300">
                            @foreach ($this->rows->take(3) as $row)
                                <div class="flex items-center gap-3 py-3">
                                    <x-avatar :name="$row->name" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium">{{ $row->name }}</p>
                                        <p class="text-sm text-base-content/60">{{ __('theme-demo::messages.tab_content_panels_activity_detail') }}</p>
                                    </div>
                                    <x-badge value="{{ __('theme-demo::messages.tab_content_panels_activity_status') }}" color="success" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <article class="max-w-3xl space-y-6">
                    <div class="space-y-2">
                        <p class="ui-subtle">{{ __('theme-demo::messages.tab_content_text_eyebrow') }}</p>
                        <h2 class="ui-h2">{{ __('theme-demo::messages.tab_content_text_heading') }}</h2>
                    </div>

                    <p>{{ __('theme-demo::messages.tab_content_text_intro') }}</p>

                    <div class="space-y-3">
                        <h3 class="ui-h3">{{ __('theme-demo::messages.tab_content_text_subheading') }}</h3>
                        <p>{{ __('theme-demo::messages.tab_content_text_body') }}</p>
                        <ul class="list-inside list-disc space-y-2 text-base-content/80">
                            <li>{{ __('theme-demo::messages.tab_content_text_list_one') }}</li>
                            <li>{{ __('theme-demo::messages.tab_content_text_list_two') }}</li>
                            <li>{{ __('theme-demo::messages.tab_content_text_list_three') }}</li>
                        </ul>
                    </div>

                    <p>
                        {{ __('theme-demo::messages.tab_content_text_outro') }}
                        <a href="{{ route('style-demo.index') }}" class="link link-primary">{{ __('theme-demo::messages.tab_content_text_link') }}</a>.
                    </p>
                </article>
            @endif
        </x-slot:content>
    </x-tabs-nav>
</div>
