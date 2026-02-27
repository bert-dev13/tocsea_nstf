<?php

namespace App\Http\Controllers;

use App\Models\CalculationHistory;
use App\Models\SavedEquation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalculationHistoryController extends Controller
{
    public const PER_PAGE = 10;

    /**
     * Display calculation history with search, filters, pagination.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CalculationHistory::class);
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

        $items = $query->paginate(self::PER_PAGE)->withQueryString();

        $equationOptions = ['' => 'All equations', '__default__' => 'Default (Buguey)'] + SavedEquation::orderBy('equation_name')
            ->get(['id', 'equation_name'])
            ->mapWithKeys(fn ($eq) => [$eq->id => $eq->equation_name])
            ->all();

        return view('calculation-history.index', [
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
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CalculationHistory::class);
        $validated = $request->validate([
            'saved_equation_id' => ['nullable', 'integer', 'exists:saved_equations,id'],
            'equation_name' => ['required', 'string', 'max:255'],
            'formula_snapshot' => ['required', 'string'],
            'inputs' => ['required', 'array'],
            'inputs.*' => ['nullable'],
            'result' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

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