<?php

namespace App\Http\Controllers;

use App\Services\TogetherAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SoilCalculatorController extends Controller
{
    public function __construct(
        protected TogetherAiService $aiService
    ) {}

    public function index(Request $request): View
    {
        $rerunData = $request->session()->get('calculation_history_rerun');

        return view('user.soil-calculator.index', [
            'rerunData' => $rerunData,
        ]);
    }

    /**
     * Generate AI-powered tree and vegetation recommendations based on calculation context.
     */
    public function generateTreeRecommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'soil_type' => ['required', 'string', 'in:sandy,clay,loamy,silty,peaty,chalky'],
            'predicted_soil_loss' => ['required', 'numeric'],
            'risk_level' => ['required', 'string', 'in:low,moderate,high,Low,Moderate,High'],
            'hazard_values' => ['required', 'array'],
            'model_name' => ['nullable', 'string', 'max:500'],
        ]);

        $context = [
            'soil_type' => $validated['soil_type'],
            'predicted_soil_loss' => (string) ((float) $validated['predicted_soil_loss']),
            'risk_level' => $validated['risk_level'],
            'hazard_values' => $validated['hazard_values'],
            'model_name' => $validated['model_name'] ?? null,
        ];

        try {
            $recommendations = $this->aiService->generateTreeRecommendations($context);

            return response()->json($recommendations);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'fallback' => true,
            ], 500);
        }
    }
}
