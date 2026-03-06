<?php

namespace App\Http\Controllers;

use App\Models\CalculationHistory;
use App\Models\SavedEquation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $totalThisMonth = CalculationHistory::forUser($user)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $savedModelsCount = SavedEquation::forUser($user)->count();

        $latestCalc = CalculationHistory::forUser($user)
            ->orderByDesc('created_at')
            ->first();

        $latestResult = $latestCalc ? (float) $latestCalc->result : null;
        $riskLevel = $this->deriveRiskLevel($latestResult);

        $recentCalculations = CalculationHistory::forUser($user)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('user.dashboard.index', [
            'user' => $user,
            'location' => [
                'province' => $user->province ?? '—',
                'municipality' => $user->municipality ?? '—',
                'barangay' => $user->barangay ?? '—',
            ],
            'lastLogin' => $user->last_login_at?->diffForHumans(),
            'summaryOverview' => [
                'totalCalculationsThisMonth' => $totalThisMonth,
                'savedModels' => $savedModelsCount,
                'latestSoilLossResult' => $latestResult,
                'currentRiskLevel' => $riskLevel,
            ],
            'recentCalculations' => $recentCalculations,
        ]);
    }

    private function deriveRiskLevel(?float $result): string
    {
        if ($result === null) {
            return '—';
        }
        if ($result < 10) {
            return 'Low';
        }
        if ($result < 50) {
            return 'Moderate';
        }
        return 'High';
    }
}
