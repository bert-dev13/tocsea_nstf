<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalculationHistoryRequest;
use App\Models\CalculationHistory;
use App\Models\SavedEquation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalculationHistoryController extends Controller
{
    public const PER_PAGE = 10;

    /**
     * Build filtered query for index (search, date range, equation, sort).
     */
    private function filteredQuery(Request $request)
    {
        $user = $request->user();
        $query = CalculationHistory::forUser($user)
            ->select(['id', 'user_id', 'saved_equation_id', 'equation_name', 'formula_snapshot', 'inputs', 'result', 'notes', 'created_at']);

        if ($request->filled('q')) {
            $q = $request->get('q');
            $query->where(function ($qry) use ($q) {
                $qry->where('equation_name', 'like', '%' . $q . '%')
                    ->orWhere('formula_snapshot', 'like', '%' . $q . '%');
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        if ($request->filled('equation')) {
            if ($request->get('equation') === '__default__') {
                $query->whereNull('saved_equation_id');
            } else {
                $query->where('saved_equation_id', $request->get('equation'));
            }
        }

        $sort = $request->get('sort', 'newest');
        $query->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc');

        return $query;
    }

    /**
     * Display calculation history with search, filters, pagination.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CalculationHistory::class);

        $items = $this->filteredQuery($request)->paginate(self::PER_PAGE)->withQueryString();

        $equationOptions = ['' => 'All equations', '__default__' => 'Default (Buguey)'] + SavedEquation::forUser($request->user())
            ->orderBy('equation_name')
            ->get(['id', 'equation_name'])
            ->mapWithKeys(fn ($eq) => [$eq->id => $eq->equation_name])
            ->all();

        return view('user.calculation-history.index', [
            'histories' => $items,
            'equationOptions' => $equationOptions,
        ]);
    }

    /**
     * Show a single record (for modal / API).
     */
    public function show(Request $request, CalculationHistory $calculation_history): JsonResponse
    {
        $this->authorize('view', $calculation_history);

        return response()->json([
            'id' => $calculation_history->id,
            'equation_name' => $calculation_history->equation_name,
            'formula_snapshot' => $calculation_history->formula_snapshot,
            'inputs' => $calculation_history->inputs,
            'result' => (string) $calculation_history->result,
            'notes' => $calculation_history->notes,
            'created_at' => $calculation_history->created_at->toIso8601String(),
            'saved_equation_id' => $calculation_history->saved_equation_id,
        ]);
    }

    /**
     * Store a new calculation (called via AJAX from Soil Calculator).
     */
    public function store(StoreCalculationHistoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $history = $request->user()->calculationHistories()->create([
            'saved_equation_id' => $validated['saved_equation_id'] ?? null,
            'equation_name' => $validated['equation_name'],
            'formula_snapshot' => $validated['formula_snapshot'],
            'inputs' => $validated['inputs'],
            'result' => $validated['result'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'id' => $history->id,
            'message' => 'Calculation saved to history.',
        ], 201);
    }

    /**
     * Delete a history record.
     */
    public function destroy(CalculationHistory $calculation_history): JsonResponse
    {
        $this->authorize('delete', $calculation_history);
        $calculation_history->delete();

        return response()->json([
            'success' => true,
            'message' => 'Calculation removed from history.',
        ]);
    }

    /**
     * Export: return filtered rows for PDF/Excel/Print.
     * scope=page: current page only (respects page, per_page).
     * scope=all: all matching results.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalculationHistory::class);

        $scope = $request->get('scope', 'page');
        $query = $this->filteredQuery($request);

        if ($scope === 'page') {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = min(100, max(1, (int) $request->get('per_page', self::PER_PAGE)));
            $items = $query->forPage($page, $perPage)->get();
        } else {
            $items = $query->get();
        }

        $rows = $items->map(function ($h) {
            $inputCount = is_array($h->inputs) ? count($h->inputs) : 0;
            $inputsLabel = $inputCount . ' variable' . ($inputCount !== 1 ? 's' : '');

            return [
                'id' => $h->id,
                'date_time' => $h->created_at->format('M j, Y g:i A'),
                'equation_name' => $h->equation_name,
                'inputs' => $inputsLabel,
                'result' => (float) $h->result,
                'result_formatted' => number_format($h->result, 2) . ' m²/year',
            ];
        });

        return response()->json(['rows' => $rows]);
    }

    /**
     * Export calculation history as PDF (dedicated layout, fixed column widths).
     * Uses same filters as export(); scope=page for current page only.
     */
    public function exportPdf(Request $request)
    {
        $this->authorize('viewAny', CalculationHistory::class);

        $scope = $request->get('scope', 'page');
        $query = $this->filteredQuery($request);

        if ($scope === 'page') {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = min(100, max(1, (int) $request->get('per_page', self::PER_PAGE)));
            $items = $query->forPage($page, $perPage)->get();
        } else {
            $items = $query->get();
        }

        $rows = $items->map(function ($h) {
            $inputCount = is_array($h->inputs) ? count($h->inputs) : 0;
            $inputsLabel = $inputCount . ' variable' . ($inputCount !== 1 ? 's' : '');

            return [
                'date_time' => $h->created_at->format('M j, Y g:i A'),
                'equation_name' => $h->equation_name,
                'inputs' => $inputsLabel,
                'result_formatted' => number_format((float) $h->result, 2) . ' m²/year',
            ];
        });

        $dateGenerated = now()->format('M j, Y g:i A');

        return Pdf::loadView('reports.calculation-history-pdf', [
            'rows' => $rows,
            'dateGenerated' => $dateGenerated,
        ])
            ->setPaper('a4', 'portrait')
            ->download('Calculation_History_Report.pdf');
    }

    /**
     * Re-run: flash data and redirect to Soil Calculator for pre-fill.
     */
    public function rerun(CalculationHistory $calculation_history): RedirectResponse
    {
        $this->authorize('view', $calculation_history);

        $request = request();
        $request->session()->flash('calculation_history_rerun', [
            'saved_equation_id' => $calculation_history->saved_equation_id,
            'equation_name' => $calculation_history->equation_name,
            'inputs' => $calculation_history->inputs,
        ]);

        return redirect()->route('soil-calculator');
    }
}