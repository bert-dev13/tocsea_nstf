<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    /**
     * Build the users query from validated filters (used by apiIndex).
     *
     * @param  array<string, mixed>  $validated
     */
    private function buildUsersQuery(array $validated): Builder
    {
        $sortBy = $validated['sortBy'] ?? 'created_at';
        $sortDir = $validated['sortDir'] ?? 'desc';

        $query = User::query();

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        if (! empty($validated['status'])) {
            $query->where('is_disabled', $validated['status'] === 'inactive');
        }

        return $query->orderBy($sortBy, $sortDir)->orderByDesc('id');
    }

    public function index(Request $request)
    {
        return view('admin.users.index', [
            'equationOptions' => [], // Not used on users page; kept for layout compatibility
        ]);
    }

    /**
     * API: paginated users list for admin (JSON).
     * GET /api/admin/users?page=&pageSize=&search=&role=&status=&sortBy=&sortDir=
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'pageSize' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,user'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'sort' => ['sometimes', 'string', 'in:newest,oldest,name_asc,name_desc'],
            'sortBy' => ['sometimes', 'string', 'in:name,email,created_at,role'],
            'sortDir' => ['sometimes', 'string', 'in:asc,desc'],
        ]);

        $page = max(1, (int) ($validated['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($validated['pageSize'] ?? 25)));
        $validated['page'] = $page;
        $validated['pageSize'] = $pageSize;

        $sort = $validated['sort'] ?? null;
        if ($sort) {
            $map = ['newest' => ['created_at', 'desc'], 'oldest' => ['created_at', 'asc'], 'name_asc' => ['name', 'asc'], 'name_desc' => ['name', 'desc']];
            [$validated['sortBy'], $validated['sortDir']] = $map[$sort] ?? ['created_at', 'desc'];
        }
        $validated['sortBy'] = $validated['sortBy'] ?? 'created_at';
        $validated['sortDir'] = $validated['sortDir'] ?? 'desc';

        $query = $this->buildUsersQuery($validated);
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()->map(fn ($user) => $this->userToArray($user));

        return response()->json([
            'data' => $items->values()->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'totalItems' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $user->loadCount(['calculationHistories', 'soilLossRecords', 'regressionModels']);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_label' => $user->role_label,
            'status' => $user->status,
            'status_label' => $user->status_label,
            'province' => $user->province,
            'municipality' => $user->municipality,
            'barangay' => $user->barangay,
            'created_at' => $user->created_at->format('M j, Y • g:i A'),
            'last_login_at' => $user->last_login_at?->format('M j, Y • g:i A'),
            'calculation_histories_count' => $user->calculation_histories_count ?? 0,
            'soil_loss_records_count' => $user->soil_loss_records_count ?? 0,
            'regression_models_count' => $user->regression_models_count ?? 0,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'status' => ['required', 'in:active,inactive'],
            'province' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'admin',
            'status' => $validated['status'],
            'is_admin' => true,
            'province' => $validated['province'] ?? null,
            'municipality' => $validated['municipality'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Admin created successfully.',
                'user' => $this->userToArray($user),
            ]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Admin created successfully.');
    }

    public function update(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'in:admin,user'],
            'status' => ['required', 'in:active,inactive'],
        ];
        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)];
        }

        $validated = $request->validate($rules);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->status = $validated['status'];
        $user->is_admin = $validated['role'] === 'admin';
        $user->province = $validated['province'] ?? null;
        $user->municipality = $validated['municipality'] ?? null;
        $user->barangay = $validated['barangay'] ?? null;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'user' => $this->userToArray($user),
            ]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): JsonResponse|RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account.',
                ], 422);
            }
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $user->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Build validated export params from request (same filters as apiIndex).
     */
    private function getExportParams(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,user'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'sort' => ['sometimes', 'string', 'in:newest,oldest,name_asc,name_desc'],
        ]);

        $sort = $validated['sort'] ?? 'newest';
        $map = [
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
        ];
        [$sortBy, $sortDir] = $map[$sort] ?? ['created_at', 'desc'];

        return [
            'search' => $validated['search'] ?? '',
            'role' => $validated['role'] ?? '',
            'status' => $validated['status'] ?? '',
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ];
    }

    /**
     * Get export query with same filters/search/sort as the main list.
     */
    private function getExportQuery(Request $request): Builder
    {
        $params = $this->getExportParams($request);

        return $this->buildUsersQuery([
            'search' => $params['search'],
            'role' => $params['role'],
            'status' => $params['status'],
            'sortBy' => $params['sortBy'],
            'sortDir' => $params['sortDir'],
        ]);
    }

    /**
     * Export users as Excel (same filtered dataset).
     * Uses Laravel Excel when available; otherwise returns CSV for Excel import.
     */
    public function exportExcel(Request $request): Response|RedirectResponse
    {
        $users = $this->getExportQuery($request)->get();

        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            $query = $this->getExportQuery($request);
            $filename = 'users-export-' . now()->format('Y-m-d-His') . '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(new UsersExport($query), $filename);
        }

        $headers = ['Name', 'Email', 'Role', 'Status', 'Province', 'Municipality/City', 'Barangay', 'Date Created', 'Last Login'];
        $rows = $users->map(fn ($u) => [
            $u->name,
            $u->email,
            $u->role_label,
            $u->status_label,
            $u->province ?? '—',
            $u->municipality ?? '—',
            $u->barangay ?? '—',
            $u->created_at->format('M j, Y'),
            $u->last_login_at?->format('M j, Y • g:i A') ?? '—',
        ])->all();

        $csv = $this->arrayToCsv(array_merge([$headers], $rows));
        $filename = 'users-export-' . now()->format('Y-m-d-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function arrayToCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, array_map(fn ($v) => $v ?? '', $row));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return "\xEF\xBB\xBF" . $csv;
    }

    private function userToArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_label' => $user->role_label,
            'status' => $user->status,
            'status_label' => $user->status_label,
            'province' => $user->province,
            'municipality' => $user->municipality,
            'barangay' => $user->barangay,
            'created_at' => $user->created_at->format('M j, Y'),
            'last_login_at' => $user->last_login_at?->format('M j, Y • g:i A') ?? null,
        ];
    }
}
