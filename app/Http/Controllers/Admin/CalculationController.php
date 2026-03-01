<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalculationHistory;
use App\Models\SavedEquation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CalculationController extends Controller
{
    /**
     * Build filtered query for admin (all users): search, date range, risk, equation, location, sort.
     */
    private function filteredQuery(Request $request)
    {
        $query = CalculationHistory::query()
            ->with(['user:id,name,email,province,municipality,barangay', 'savedEquation:id,equation_name'])
            ->select(['id', 'user_id', 'saved_equation_id', 'equation_name', 'formula_snapshot', 'inputs', 'result', 'notes', 'created_at']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('equation_name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('province', 'like', '%' . $search . '%')
                            ->orWhere('municipality', 'like', '%' . $search . '%')
                            ->orWhere('barangay', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        if ($request->filled('risk')) {
            $risk = $request->get('risk');
            if ($risk === 'low') {
                $query->whereRaw('result < 10');
            } elseif ($risk === 'moderate') {
                $query->whereRaw('result >= 10 AND result < 50');
            } elseif ($risk === 'high') {
                $query->whereRaw('result >= 50');
            }
        }

        if ($request->filled('equation')) {
            if ($request->get('equation') === '__default__') {
                $query->whereNull('saved_equation_id');
            } else {
                $query->where('saved_equation_id', $request->get('equation'));
            }
        }

        if ($request->filled('location')) {
            $location = $request->get('location');
            $query->whereHas('user', function ($q) use ($location) {
                $q->where('province', 'like', '%' . $location . '%')
                    ->orWhere('municipality', 'like', '%' . $location . '%')
                    ->orWhere('barangay', 'like', '%' . $location . '%');
            });
        }

        $sort = $request->get('sort', 'newest');
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'loss_asc') {
            $query->orderBy('result', 'asc');
        } elseif ($sort === 'loss_desc') {
            $query->orderBy('result', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    /**
     * List all calculations (admin); page shell + filter options. Data loaded via API.
     */
    public function index(Request $request): View
    {
        $equationOptions = ['' => 'All equations', '__default__' => 'Default (Buguey)'] + SavedEquation::orderBy('equation_name')
            ->get(['id', 'equation_name'])
            ->mapWithKeys(fn ($eq) => [$eq->id => $eq->equation_name])
            ->all();

        return view('admin.calculations.index', [
            'equationOptions' => $equationOptions,
        ]);
    }

    /**
     * API: paginated calculations list for admin (JSON).
     * GET /api/admin/calculations?page=&pageSize=&search=&location=&dateFrom=&dateTo=&modelId=&sortBy=&sortDir=
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'pageSize' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'dateFrom' => ['sometimes', 'string', 'date'],
            'dateTo' => ['sometimes', 'string', 'date'],
            'modelId' => ['sometimes', 'string', 'max:255'],
            'risk' => ['sometimes', 'string', 'in:low,moderate,high'],
            'equation' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'string', 'date'],
            'to' => ['sometimes', 'string', 'date'],
            'sort' => ['sometimes', 'string', 'in:newest,oldest,loss_desc,loss_asc'],
            'sortBy' => ['sometimes', 'string', 'in:created_at,result,equation_name'],
            'sortDir' => ['sometimes', 'string', 'in:asc,desc'],
        ]);

        $page = max(1, (int) ($validated['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($validated['pageSize'] ?? 25)));

        $sortBy = $validated['sortBy'] ?? null;
        $sortDir = $validated['sortDir'] ?? null;
        $sort = $validated['sort'] ?? null;
        if ($sort) {
            $map = ['newest' => ['created_at', 'desc'], 'oldest' => ['created_at', 'asc'], 'loss_desc' => ['result', 'desc'], 'loss_asc' => ['result', 'asc']];
            [$sortBy, $sortDir] = $map[$sort] ?? ['created_at', 'desc'];
        }
        $sortBy = $sortBy ?? 'created_at';
        $sortDir = $sortDir ?? 'desc';

        $query = CalculationHistory::query()
            ->with(['user:id,name,email,province,municipality,barangay', 'savedEquation:id,equation_name'])
            ->select(['id', 'user_id', 'saved_equation_id', 'equation_name', 'formula_snapshot', 'inputs', 'result', 'notes', 'created_at']);

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('equation_name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('province', 'like', '%' . $search . '%')
                        ->orWhere('municipality', 'like', '%' . $search . '%')
                        ->orWhere('barangay', 'like', '%' . $search . '%'));
            });
        }

        $dateFrom = $validated['dateFrom'] ?? $validated['from'] ?? null;
        $dateTo = $validated['dateTo'] ?? $validated['to'] ?? null;
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if (! empty($validated['risk'])) {
            $risk = $validated['risk'];
            if ($risk === 'low') {
                $query->whereRaw('result < 10');
            } elseif ($risk === 'moderate') {
                $query->whereRaw('result >= 10 AND result < 50');
            } elseif ($risk === 'high') {
                $query->whereRaw('result >= 50');
            }
        }

        $equationFilter = $validated['modelId'] ?? $validated['equation'] ?? null;
        if ($equationFilter !== null && $equationFilter !== '') {
            if ($equationFilter === '__default__') {
                $query->whereNull('saved_equation_id');
            } else {
                $query->where('saved_equation_id', $equationFilter);
            }
        }

        $location = $validated['location'] ?? null;
        if ($location) {
            $query->whereHas('user', fn ($q) => $q->where('province', 'like', '%' . $location . '%')
                ->orWhere('municipality', 'like', '%' . $location . '%')
                ->orWhere('barangay', 'like', '%' . $location . '%'));
        }

        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()->map(function ($calc) {
            $riskClass = strtolower($calc->risk_level);
            if ($riskClass === '—') {
                $riskClass = 'na';
            }

            return [
                'id' => $calc->id,
                'created_at' => $calc->created_at->format('M j, Y g:i A'),
                'user_name' => $calc->user?->name ?? '—',
                'location_display' => $calc->location_display,
                'equation_name' => $calc->equation_name,
                'result' => (float) $calc->result,
                'risk_level' => $calc->risk_level,
                'risk_class' => $riskClass,
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

    /**
     * Show one calculation (for view modal / API). Admin can view any.
     */
    public function show(CalculationHistory $calculation_history): JsonResponse
    {
        $calculation_history->load(['user:id,name,email,province,municipality,barangay', 'savedEquation:id,equation_name']);

        $riskLevel = $calculation_history->risk_level;
        $locationDisplay = $calculation_history->location_display;

        return response()->json([
            'id' => $calculation_history->id,
            'equation_name' => $calculation_history->equation_name,
            'formula_snapshot' => $calculation_history->formula_snapshot,
            'inputs' => $calculation_history->inputs,
            'result' => (string) $calculation_history->result,
            'result_formatted' => number_format((float) $calculation_history->result, 2) . ' m²/year',
            'risk_level' => $riskLevel,
            'location_display' => $locationDisplay,
            'user_name' => $calculation_history->user?->name,
            'user_email' => $calculation_history->user?->email,
            'notes' => $calculation_history->notes,
            'created_at' => $calculation_history->created_at->toIso8601String(),
            'saved_equation_id' => $calculation_history->saved_equation_id,
        ]);
    }

    /**
     * Export calculations as Excel (same filtered dataset as list).
     * Uses Laravel Excel when available; otherwise returns CSV for Excel import.
     */
    public function exportExcel(Request $request): Response|RedirectResponse
    {
        $query = $this->filteredQuery($request);
        $items = $query->get();

        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            $exportQuery = $this->filteredQuery($request);
            $filename = 'TOCSEA-calculations-' . now()->format('Y-m-d-His') . '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\CalculationsExport($exportQuery),
                $filename
            );
        }

        $headers = ['Date/Time', 'User', 'Location', 'Model/Equation', 'Predicted Soil Loss (m²/year)', 'Risk Level', 'Status'];
        $rows = $items->map(function ($row) {
            $result = (float) $row->result;
            $formatted = number_format($result, 2) . ' m²/year';
            return [
                $row->created_at->format('M j, Y g:i A'),
                $row->user?->name ?? '—',
                $row->location_display,
                $row->equation_name ?? '—',
                $formatted,
                $row->risk_level,
                'Completed',
            ];
        })->all();

        $csv = $this->calculationsToCsv(array_merge([$headers], $rows));
        $filename = 'TOCSEA-calculations-' . now()->format('Y-m-d-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function calculationsToCsv(array $rows): string
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

    /**
     * Single record PDF: render print-friendly view; user can print to PDF.
     */
    public function exportPdf(CalculationHistory $calculation_history): View
    {
        $calculation_history->load(['user:id,name,email,province,municipality,barangay', 'savedEquation:id,equation_name']);

        return view('admin.calculations.pdf-single', [
            'calculation' => $calculation_history,
            'risk_level' => $calculation_history->risk_level,
            'location_display' => $calculation_history->location_display,
        ]);
    }

    /**
     * Delete a calculation. Admin only.
     */
    public function destroy(Request $request, CalculationHistory $calculation_history): JsonResponse
    {
        $calculation_history->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Calculation deleted successfully.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Calculation deleted successfully.',
        ]);
    }
}
