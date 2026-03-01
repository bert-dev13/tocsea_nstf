@extends('layouts.admin')

@section('title', 'User Management')
@section('page-title', 'User Management')

@section('content')
<div class="admin-hub admin-users-page" data-admin-users-base="{{ url('admin/users') }}">
    <header class="admin-header fade-in-element">
        <div class="admin-header-card">
            <div class="admin-header-main">
                <div>
                    <h2 class="admin-header-title">User Management</h2>
                    <p class="admin-header-meta">
                        <i data-lucide="users" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Manage system users and permissions
                    </p>
                </div>
                <button type="button" class="admin-users-add-btn" id="adminAddUserBtn" aria-label="Add admin">
                    <i data-lucide="user-plus" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    Add Admin
                </button>
            </div>
        </div>
    </header>

    @if(session('success'))
    <div class="admin-alert admin-alert-success" role="alert">
        <i data-lucide="circle-check-big" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="admin-alert admin-alert-error" role="alert">
        <i data-lucide="alert-circle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        {{ session('error') }}
    </div>
    @endif

    <div id="adminUsersAlert" class="admin-alert admin-alert-success" role="alert" style="display:none;">
        <i data-lucide="circle-check-big" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        <span id="adminUsersAlertText"></span>
    </div>
    <div id="adminUsersErrorAlert" class="admin-alert admin-alert-error" role="alert" style="display:none;">
        <i data-lucide="alert-circle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        <span id="adminUsersErrorAlertText"></span>
    </div>

    <section class="admin-section admin-users-section fade-in-element">
        <div class="admin-users-toolbar">
            <form method="GET" action="{{ route('admin.users.index') }}" class="admin-users-search-form" id="adminUsersFilterForm" role="search">
                <input type="hidden" name="page" value="1">
                <div class="admin-users-search-wrap">
                    <i data-lucide="search" class="lucide-icon lucide-icon-sm admin-search-icon" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email…" class="admin-users-search-input" aria-label="Search users">
                </div>
                <select name="role" class="admin-users-filter-select" aria-label="Filter by role">
                    <option value="">All roles</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>User</option>
                </select>
                <select name="status" class="admin-users-filter-select" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <select name="sort" class="admin-users-filter-select" aria-label="Sort">
                    <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>Newest first</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                    <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name A–Z</option>
                    <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name Z–A</option>
                </select>
                <button type="submit" class="admin-users-search-btn">Apply</button>
            </form>
        </div>

        <div class="admin-users-table-header">
            <div class="admin-users-export-dropdown no-print">
                <button type="button" class="admin-users-export-dropdown-btn" id="adminUsersExportDropdownBtn" aria-expanded="false" aria-haspopup="true" aria-label="Export options">
                    <i data-lucide="download" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    Export
                    <i data-lucide="chevron-down" class="lucide-icon lucide-icon-sm admin-users-export-chevron" aria-hidden="true"></i>
                </button>
                <div class="admin-users-export-dropdown-menu" id="adminUsersExportDropdownMenu" role="menu" aria-hidden="true">
                    <a href="{{ route('admin.users.export-excel') }}{{ request()->query() ? '?' . http_build_query(request()->query()) : '' }}" class="admin-users-export-dropdown-item" role="menuitem" id="adminUsersExcelBtn">
                        <i data-lucide="file-spreadsheet" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Export as Excel
                    </a>
                    <button type="button" class="admin-users-export-dropdown-item" role="menuitem" id="adminUsersPdfBtn">
                        <i data-lucide="file-text" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Export as PDF
                    </button>
                    <button type="button" class="admin-users-export-dropdown-item" role="menuitem" id="adminUsersPrintBtn">
                        <i data-lucide="printer" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>

        <div class="admin-users-table-wrap" id="adminUsersTableWrap">
            <div class="admin-users-print-header" aria-hidden="true">
                <h2 class="admin-users-print-title">TOCSEA – User Management Report</h2>
                <p class="admin-users-print-date">Generated: <span id="adminUsersPrintDate"></span></p>
            </div>
            <div id="adminUsersTableSkeleton" class="admin-table-skeleton" style="display:none;" aria-hidden="true">
                <table class="admin-users-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Province</th><th>Municipality</th><th>Barangay</th><th>Date</th><th>Last Login</th><th>Actions</th></tr></thead><tbody>
                @for ($i = 0; $i < 5; $i++)
                <tr><td><span class="admin-skeleton-line"></span></td><td><span class="admin-skeleton-line"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td></tr>
                @endfor
                </tbody></table>
            </div>
            <div id="adminUsersEmpty" class="admin-users-empty" style="display:none;">
                <i data-lucide="users" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                <p>No users found</p>
                <p class="admin-users-empty-hint">Try adjusting your search or filters.</p>
            </div>
            @include('admin.users._table', ['users' => collect(), 'exportMode' => false])
        </div>
        <div id="adminUsersPagination"></div>
    </section>
</div>

{{-- View Modal --}}
<div id="adminViewUserModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminViewUserModalTitle" aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-modal></div>
    <div class="admin-modal-dialog admin-modal-dialog-view">
        <div class="admin-modal-header admin-view-user-header">
            <div class="admin-view-user-header-inner">
                <h3 id="adminViewUserModalTitle" class="admin-modal-title">User Details</h3>
                <p id="adminViewUserSubtitle" class="admin-view-user-subtitle" aria-hidden="true">Viewing: …</p>
            </div>
            <button type="button" class="admin-modal-close" data-close-modal aria-label="Close">
                <i data-lucide="x" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            </button>
        </div>
        <div class="admin-modal-body admin-view-user-body">
            <div id="adminViewUserContent" class="admin-modal-view-content">
                <div class="admin-modal-loading">
                    <i data-lucide="loader-2" class="lucide-icon lucide-icon-lg admin-spin" aria-hidden="true"></i>
                    <span>Loading…</span>
                </div>
            </div>
        </div>
        <div class="admin-modal-footer admin-view-user-footer">
            <button type="button" class="admin-modal-btn admin-modal-btn-secondary" data-close-modal>Close</button>
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div id="adminEditUserModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminEditUserModalTitle" aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-modal></div>
    <div class="admin-modal-dialog admin-modal-dialog-edit">
        <form id="adminEditUserForm" class="admin-modal-form admin-edit-user-form">
            @csrf
            <input type="hidden" name="_method" value="PUT">
            <div class="admin-modal-header admin-edit-user-header">
                <div class="admin-edit-user-header-inner">
                    <h3 id="adminEditUserModalTitle" class="admin-modal-title">Edit User</h3>
                    <p id="adminEditUserSubtitle" class="admin-edit-user-subtitle" aria-hidden="true">Editing: …</p>
                </div>
                <button type="button" class="admin-modal-close" data-close-modal aria-label="Close">
                    <i data-lucide="x" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                </button>
            </div>
            <div class="admin-modal-body admin-edit-user-body">
                <input type="hidden" name="user_id" id="adminEditUserId">
                <div class="admin-edit-user-grid">
                    <div class="admin-edit-user-field">
                        <label for="adminEditName">Full Name</label>
                        <input type="text" id="adminEditName" name="name" required maxlength="255" class="admin-edit-user-input" autocomplete="name">
                        <span class="admin-edit-user-error" id="adminEditNameError" role="alert"></span>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminEditEmail">Email</label>
                        <input type="email" id="adminEditEmail" name="email" required maxlength="255" class="admin-edit-user-input" autocomplete="email">
                        <span class="admin-edit-user-error" id="adminEditEmailError" role="alert"></span>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminEditRole">Role</label>
                        <select id="adminEditRole" name="role" required class="admin-edit-user-select">
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminEditStatus">Status</label>
                        <select id="adminEditStatus" name="status" required class="admin-edit-user-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminEditProvince">Province</label>
                        <select id="adminEditProvince" name="province" class="admin-edit-user-select" data-psgc="province">
                            <option value="">Select Province</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminEditMunicipality">Municipality/City</label>
                        <select id="adminEditMunicipality" name="municipality" class="admin-edit-user-select" data-psgc="municipality" disabled>
                            <option value="">Select Municipality</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-field admin-edit-user-field-full">
                        <label for="adminEditBarangay">Barangay</label>
                        <select id="adminEditBarangay" name="barangay" class="admin-edit-user-select" data-psgc="barangay" disabled>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-divider"></div>
                    <div class="admin-edit-user-field admin-edit-user-field-full">
                        <p class="admin-edit-user-helper">Password (optional) — leave blank to keep current</p>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminEditPassword">New Password</label>
                        <input type="password" id="adminEditPassword" name="password" class="admin-edit-user-input" autocomplete="new-password" minlength="8">
                        <span class="admin-edit-user-error" id="adminEditPasswordError" role="alert"></span>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminEditPasswordConfirmation">Confirm Password</label>
                        <input type="password" id="adminEditPasswordConfirmation" name="password_confirmation" class="admin-edit-user-input" autocomplete="new-password" minlength="8">
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer admin-edit-user-footer">
                <button type="button" class="admin-modal-btn admin-modal-btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="admin-modal-btn admin-modal-btn-primary" id="adminEditSaveBtn">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Delete Modal --}}
<div id="adminDeleteUserModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminDeleteUserModalTitle" aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-modal></div>
    <div class="admin-modal-dialog admin-modal-dialog-sm">
        <div class="admin-modal-header">
            <h3 id="adminDeleteUserModalTitle" class="admin-modal-title">Delete User</h3>
            <button type="button" class="admin-modal-close" data-close-modal aria-label="Close">
                <i data-lucide="x" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <p class="admin-delete-warning">
                <i data-lucide="alert-triangle" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                Are you sure you want to delete this user? This action cannot be undone.
            </p>
            <dl class="admin-delete-info">
                <dt>Name</dt>
                <dd id="adminDeleteUserName"></dd>
                <dt>Email</dt>
                <dd id="adminDeleteUserEmail"></dd>
            </dl>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-modal-btn admin-modal-btn-secondary" data-close-modal>Cancel</button>
            <button type="button" id="adminDeleteUserConfirmBtn" class="admin-modal-btn admin-modal-btn-danger">Confirm Delete</button>
        </div>
    </div>
</div>

{{-- Add User Modal --}}
<div id="adminAddUserModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminAddUserModalTitle" aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-modal></div>
    <div class="admin-modal-dialog admin-modal-dialog-edit">
        <form id="adminAddUserForm" class="admin-modal-form admin-edit-user-form">
            @csrf
            <div class="admin-modal-header admin-edit-user-header">
                <div class="admin-edit-user-header-inner">
                    <h3 id="adminAddUserModalTitle" class="admin-modal-title">Add Admin</h3>
                    <p class="admin-edit-user-subtitle" aria-hidden="true">Add a new admin user</p>
                </div>
                <button type="button" class="admin-modal-close" data-close-modal aria-label="Close">
                    <i data-lucide="x" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                </button>
            </div>
            <div class="admin-modal-body admin-edit-user-body">
                <div class="admin-edit-user-grid">
                    <div class="admin-edit-user-field">
                        <label for="adminAddName">Full Name</label>
                        <input type="text" id="adminAddName" name="name" required maxlength="255" class="admin-edit-user-input" autocomplete="name">
                        <span class="admin-edit-user-error" id="adminAddNameError" role="alert"></span>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminAddEmail">Email</label>
                        <input type="email" id="adminAddEmail" name="email" required maxlength="255" class="admin-edit-user-input" autocomplete="email">
                        <span class="admin-edit-user-error" id="adminAddEmailError" role="alert"></span>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminAddStatus">Status</label>
                        <select id="adminAddStatus" name="status" required class="admin-edit-user-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-field admin-edit-user-field-full">
                        <label for="adminAddProvince">Province</label>
                        <select id="adminAddProvince" name="province" class="admin-edit-user-select" data-psgc="province">
                            <option value="">Select Province</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminAddMunicipality">Municipality/City</label>
                        <select id="adminAddMunicipality" name="municipality" class="admin-edit-user-select" data-psgc="municipality" disabled>
                            <option value="">Select Municipality</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminAddBarangay">Barangay</label>
                        <select id="adminAddBarangay" name="barangay" class="admin-edit-user-select" data-psgc="barangay" disabled>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                    <div class="admin-edit-user-divider"></div>
                    <div class="admin-edit-user-field admin-edit-user-field-full">
                        <p class="admin-edit-user-helper">Password (required for new user)</p>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminAddPassword">Password <span class="admin-form-required">*</span></label>
                        <input type="password" id="adminAddPassword" name="password" required class="admin-edit-user-input" autocomplete="new-password" minlength="8">
                        <span class="admin-edit-user-error" id="adminAddPasswordError" role="alert"></span>
                    </div>
                    <div class="admin-edit-user-field">
                        <label for="adminAddPasswordConfirmation">Confirm Password</label>
                        <input type="password" id="adminAddPasswordConfirmation" name="password_confirmation" required class="admin-edit-user-input" autocomplete="new-password" minlength="8">
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer admin-edit-user-footer">
                <button type="button" class="admin-modal-btn admin-modal-btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="admin-modal-btn admin-modal-btn-primary">Add Admin</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.ADMIN_USERS_CONFIG = {
    csrfToken: @json(csrf_token()),
    routes: {
        show: @json(route('admin.users.show', ['user' => '__ID__'])),
        update: @json(route('admin.users.update', ['user' => '__ID__'])),
        destroy: @json(route('admin.users.destroy', ['user' => '__ID__'])),
        store: @json(route('admin.users.store')),
    }
};
</script>
@endpush
