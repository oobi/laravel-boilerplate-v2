{{-- Activity panel (App\Panels\Users\ActivityPanel) --}}
<x-card :title="__('audit.recent_activity')" type="panel">
    @if ($entries->isNotEmpty())
        <x-slot:actions>
            <a href="{{ route('audit.index', ['user' => $user->getKey()]) }}" class="link link-hover text-sm">
                {{ __('audit.view_all') }}
            </a>
        </x-slot:actions>

        <ul class="flex flex-col gap-3">
            @foreach ($entries as $entry)
                <li class="text-sm">
                    <a href="{{ route('audit.show', $entry) }}" class="link link-hover line-clamp-2 wrap-anywhere">{{ $entry->description }}</a>
                    <div class="text-muted wrap-anywhere">
                        {{ $entry->created_at?->diffForHumans() }}
                        @if ($entry->actor_name)
                            · {{ $entry->actor_name }}
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state icon="heroicon-o-clipboard-document-list" :title="__('audit.no_activity')">
            {{ __('audit.no_activity_body') }}
        </x-empty-state>
    @endif
</x-card>
