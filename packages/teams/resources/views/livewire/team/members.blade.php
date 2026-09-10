<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold text-base-content">{{ __('Members') }}</h1>
        <p class="text-sm text-base-content/60">{{ __('Manage who belongs to :team and their roles.', ['team' => $team->name]) }}</p>
    </div>

    <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Member') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $member)
                    @php($isOwner = $member->id === $team->user_id)
                    <tr wire:key="member-{{ $member->id }}">
                        <td>
                            <div class="flex items-center gap-3">
                                <img src="{{ $member->profile_photo_url }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-base-content">{{ $member->name }}</div>
                                    <div class="truncate text-sm text-base-content/60">{{ $member->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($isOwner)
                                <span class="badge badge-neutral">{{ __('Owner') }}</span>
                            @else
                                <select
                                    class="select select-bordered select-sm"
                                    wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                >
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected($member->roles->first()?->name === $role)>{{ ucfirst($role) }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td class="text-right">
                            @unless ($isOwner)
                                <x-button.danger
                                    wire:click="removeMember({{ $member->id }})"
                                    wire:confirm="{{ __('Remove :name from the team?', ['name' => $member->name]) }}"
                                >
                                    {{ __('Remove') }}
                                </x-button.danger>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
