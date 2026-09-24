{{-- Team memberships panel (Concise\Teams\Panels\Users\TeamMembershipsPanel) --}}
<x-card :title="team_trans('memberships.title')" type="panel">
    <livewire:teams-user-memberships :user="$user" :key="'memberships-'.$user->id" />
</x-card>
