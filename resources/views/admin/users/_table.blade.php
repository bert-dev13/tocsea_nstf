{{-- Reusable User Management table partial. Single source of truth for table structure. --}}
{{-- Used by: main page (tbody filled by JS), PDF export (server-rendered rows, no Actions). --}}
{{-- $users: Illuminate\Support\Collection|iterable (required when $exportMode is true) --}}
{{-- $exportMode: bool (default false) - when true, hide Actions column and render rows from $users --}}
@php
    $exportMode = $exportMode ?? false;
@endphp
<table class="admin-users-table {{ $exportMode ? 'admin-users-table-export' : '' }}" id="adminUsersTable" role="grid" {{ !$exportMode ? 'style="display:none;"' : '' }}>
    <thead>
        <tr>
            <th scope="col">Name</th>
            <th scope="col">Email</th>
            <th scope="col">Role</th>
            <th scope="col">Status</th>
            <th scope="col">Province</th>
            <th scope="col">Municipality/City</th>
            <th scope="col">Barangay</th>
            <th scope="col">Date Created</th>
            <th scope="col">Last Login</th>
            @if (!$exportMode)
                <th scope="col" class="admin-users-col-actions no-print">Actions</th>
            @endif
        </tr>
    </thead>
    <tbody id="adminUsersTableBody">
        @if ($exportMode && isset($users))
            @foreach ($users as $user)
                <tr>
                    <td><span class="admin-users-cell-name">{{ e($user->name) }}</span></td>
                    <td>{{ e($user->email) }}</td>
                    <td><span class="admin-users-badge admin-users-badge-{{ $user->role }}">{{ $user->role_label }}</span></td>
                    <td><span class="admin-users-badge admin-users-badge-status-{{ $user->status }}">{{ $user->status_label }}</span></td>
                    <td>{{ e($user->province ?? '—') }}</td>
                    <td>{{ e($user->municipality ?? '—') }}</td>
                    <td>{{ e($user->barangay ?? '—') }}</td>
                    <td>{{ $user->created_at->format('M j, Y') }}</td>
                    <td>{{ $user->last_login_at?->format('M j, Y • g:i A') ?? '—' }}</td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>
