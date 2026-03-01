<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ModelsExport;
use App\Models\SavedEquation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ModelManagementController extends Controller
{
    /**
     * Build filtered query for admin models (same filters as list): search, createdBy, from, to, sort.
     */
    private function filteredQuery(Request $request)
    {
        $query = SavedEquation::query();

        if ($request->filled('search')) {
            $query->where('equation_name', 'like', '%' . $request->get('search') . '%');
        }

        if ($request->filled('createdBy')) {
            $query->where('user_id', $request->get('createdBy'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        $sort = $request->get('sort', 'newest');
        $map = [
            'newest' => ['updated_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'name_asc' => ['equation_name', 'asc'],
            'name_desc' => ['equation_name', 'desc'],
        ];
        [$sortBy, $sortDir] = $map[$sort] ?? ['updated_at', 'desc'];
        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        return $query;
    }

    /**
     * List saved equations (page shell). Data loaded via API.
     */
    public function index(Request $request): View
    {
        return view('admin.models.index');
    }

    /**
     * API: paginated models (saved equations) list for admin (JSON).
     * GET /api/admin/models?page=&pageSize=&search=&createdBy=&dateFrom=&dateTo=&sortBy=&sortDir=
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'pageSize' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'createdBy' => ['sometimes', 'integer', 'exists:users,id'],
            'from' => ['sometimes', 'string', 'date'],
            'to' => ['sometimes', 'string', 'date'],
            'dateFrom' => ['sometimes', 'string', 'date'],
            'dateTo' => ['sometimes', 'string', 'date'],
            'sort' => ['sometimes', 'string', 'in:newest,oldest,name_asc,name_desc'],
            'sortBy' => ['sometimes', 'string', 'in:equation_name,created_at,updated_at'],
            'sortDir' => ['sometimes', 'string', 'in:asc,desc'],
        ]);

        $page = max(1, (int) ($validated['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($validated['pageSize'] ?? 25)));

        $sort = $validated['sort'] ?? null;
        if ($sort) {
            $map = ['newest' => ['updated_at', 'desc'], 'oldest' => ['created_at', 'asc'], 'name_asc' => ['equation_name', 'asc'], 'name_desc' => ['equation_name', 'desc']];
            [$sortBy, $sortDir] = $map[$sort] ?? ['updated_at', 'desc'];
        } else {
            $sortBy = $validated['sortBy'] ?? 'updated_at';
            $sortDir = $validated['sortDir'] ?? 'desc';
        }

        $query = SavedEquation::query()->with('user:id,name,email');

        if (! empty($validated['search'])) {
            $query->where('equation_name', 'like', '%' . $validated['search'] . '%');
        }

        if (! empty($validated['createdBy'])) {
            $query->where('user_id', $validated['createdBy']);
        }

        $dateFrom = $validated['dateFrom'] ?? $validated['from'] ?? null;
        $dateTo = $validated['dateTo'] ?? $validated['to'] ?? null;
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()->map(function ($m) {
            $formula = $m->formula ?? '';

            return [
                'id' => $m->id,
                'equation_name' => $m->equation_name,
                'formula' => $formula,
                'formula_truncated' => \Illuminate\Support\Str::limit($formula, 60),
                'created_at' => $m->created_at->format('M j, Y'),
            ];
        });

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

    public function show(SavedEquation $saved_equation): JsonResponse
    {
        $saved_equation->load('user:id,name,email');

        $createdBy = $saved_equation->user
            ? $saved_equation->user->name . ' (' . $saved_equation->user->email . ')'
            : null;

        return response()->json([
            'id' => $saved_equation->id,
            'name' => $saved_equation->equation_name,
            'created_by' => $createdBy,
            'created_at' => $saved_equation->created_at->format('M j, Y • g:i A'),
            'formula' => $saved_equation->formula ?? '—',
            'predictors' => [],
            'location' => $saved_equation->location,
            'notes' => $saved_equation->notes,
            'type' => '—',
        ]);
    }

    public function destroy(SavedEquation $saved_equation): JsonResponse
    {
        $name = $saved_equation->equation_name;
        $saved_equation->delete();
        Log::info('Admin model (saved equation) deleted', [
            'admin_id' => auth()->id(),
            'admin_email' => auth()->user()?->email,
            'equation_id' => $saved_equation->id,
            'equation_name' => $name,
        ]);
        return response()->json(['success' => true, 'message' => 'Model deleted.']);
    }

    /**
     * Export models as Excel (same filtered dataset as list).
     * Uses Laravel Excel when available; otherwise returns CSV for Excel import.
     */
    public function exportExcel(Request $request): Response
    {
        $query = $this->filteredQuery($request);
        $items = $query->get();

        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            $exportQuery = $this->filteredQuery($request);
            $filename = 'TOCSEA-models-' . now()->format('Y-m-d-His') . '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new ModelsExport($exportQuery),
                $filename
            );
        }

        $headers = ['Equation Name', 'Formula', 'Date Created'];
        $rows = $items->map(function ($row) {
            return [
                $row->equation_name ?? '—',
                $row->formula ?? '—',
                $row->created_at ? $row->created_at->format('M j, Y') : '—',
            ];
        })->all();

        $csv = $this->modelsToCsv(array_merge([$headers], $rows));
        $filename = 'TOCSEA-models-' . now()->format('Y-m-d-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function modelsToCsv(array $rows): string
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
}
