<?php

namespace App\Http\Controllers;

use App\Models\SavedEquation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedEquationController extends Controller
{
    public const PER_PAGE = 10;

    /**
     * List saved equations with pagination (for Saved Equations section only).
     * Does not mix with regression logic. Only the current user's equations are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SavedEquation::class);

        $paginator = SavedEquation::forUser($request->user())
            ->orderBy('updated_at', 'desc')
            ->paginate(self::PER_PAGE, [
                'id',
                'equation_name',
                'formula',
                'created_at',
                'updated_at',
            ]);

        $equations = $paginator->getCollection()->map(function (SavedEquation $eq) {
            return [
                'id' => $eq->id,
                'equation_name' => $eq->equation_name,
                'formula' => $eq->formula,
                'created_at' => $eq->created_at->toIso8601String(),
                'updated_at' => $eq->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'equations' => $equations,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Store a new saved equation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'equation_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('saved_equations', 'equation_name')->where('user_id', $request->user()->id),
            ],
            'formula' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'equation_name.required' => 'Please enter an equation name.',
            'equation_name.unique' => 'An equation with this name already exists. Choose a unique name.',
        ]);

        $equation = SavedEquation::create([
            'user_id' => auth()->id(),
            'equation_name' => $validated['equation_name'],
            'formula' => $validated['formula'],
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Equation saved successfully.',
            'equation' => [
                'id' => $equation->id,
                'equation_name' => $equation->equation_name,
                'formula' => $equation->formula,
                'created_at' => $equation->created_at->toIso8601String(),
                'updated_at' => $equation->updated_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update an existing saved equation. Validates unique name excluding current record.
     */
    public function update(Request $request, SavedEquation $saved_equation): JsonResponse
    {
        $this->authorize('update', $saved_equation);

        $validated = $request->validate([
            'equation_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('saved_equations', 'equation_name')->where('user_id', $request->user()->id)->ignore($saved_equation->id),
            ],
            'formula' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'equation_name.required' => 'Please enter an equation name.',
            'equation_name.unique' => 'An equation with this name already exists. Choose a unique name.',
        ]);

        $saved_equation->update([
            'equation_name' => $validated['equation_name'],
            'formula' => $validated['formula'],
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Equation updated successfully.',
            'equation' => [
                'id' => $saved_equation->id,
                'equation_name' => $saved_equation->equation_name,
                'formula' => $saved_equation->formula,
                'created_at' => $saved_equation->created_at->toIso8601String(),
                'updated_at' => $saved_equation->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a saved equation.
     */
    public function destroy(SavedEquation $saved_equation): JsonResponse
    {
        $this->authorize('delete', $saved_equation);

        $saved_equation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Equation deleted successfully.',
        ]);
    }
}
