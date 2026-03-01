<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalculationHistory;
use App\Models\SavedEquation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // System usage overview
        $totalUsers = User::count();
        $adminUsers = User::where('is_admin', true)->count();
        $regularUsers = $totalUsers - $adminUsers;
        $totalCalculations = CalculationHistory::count();
        $totalSavedModels = SavedEquation::count();

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $newUsersThisMonth = User::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        // Monthly Active Users: users who had at least one calculation this month
        $monthlyActiveUsers = CalculationHistory::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->distinct('user_id')
            ->count('user_id');

        // Calculations per month (last 12 months)
        $calculationsPerMonth = CalculationHistory::query()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->month => (int) $row->count]);

        // User registrations per month (last 12 months)
        $registrationsPerMonth = User::query()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->month => (int) $row->count]);

        // Most used prediction model (equation_name from calculation_histories)
        $mostUsedModels = CalculationHistory::query()
            ->select('equation_name')
            ->selectRaw('COUNT(*) as usage_count')
            ->whereNotNull('equation_name')
            ->where('equation_name', '!=', '')
            ->groupBy('equation_name')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get();

        // Soil risk level distribution (Low < 10, Moderate 10-50, High >= 50)
        $riskLow = CalculationHistory::whereNotNull('result')->where('result', '<', 10)->count();
        $riskModerate = CalculationHistory::whereNotNull('result')->whereBetween('result', [10, 49.9999])->count();
        $riskHigh = CalculationHistory::whereNotNull('result')->where('result', '>=', 50)->count();
        $riskUnknown = CalculationHistory::whereNull('result')->count();
        $riskDistribution = [
            'Low' => $riskLow,
            'Moderate' => $riskModerate,
            'High' => $riskHigh,
            'Unknown' => $riskUnknown,
        ];

        // Top provinces (from users who have calculations, or all users with province set)
        $topProvinces = User::query()
            ->select('province')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('province')
            ->where('province', '!=', '')
            ->whereHas('calculationHistories')
            ->groupBy('province')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Most active municipalities (users with calculations, grouped by province + municipality)
        $topMunicipalities = User::query()
            ->select('province', 'municipality')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('municipality')
            ->where('municipality', '!=', '')
            ->whereHas('calculationHistories')
            ->groupBy('province', 'municipality')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Recent activity
        $latestCalculations = CalculationHistory::with(['user', 'savedEquation'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $latestRegisteredUsers = User::orderByDesc('created_at')->limit(8)->get();

        $recentlySavedModels = SavedEquation::with('user')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // Build chart labels for last 12 months
        $monthLabels = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthLabels[] = now()->subMonths($i)->format('Y-m');
        }
        $calculationsChartData = array_map(fn ($m) => $calculationsPerMonth->get($m, 0), $monthLabels);
        $registrationsChartData = array_map(fn ($m) => $registrationsPerMonth->get($m, 0), $monthLabels);
        $monthLabelsDisplay = array_map(fn ($m) => Carbon::createFromFormat('Y-m', $m)->format('M Y'), $monthLabels);

        return view('admin.analytics.index', [
            'user' => $user,
            'stats' => [
                'totalUsers' => $totalUsers,
                'adminUsers' => $adminUsers,
                'regularUsers' => $regularUsers,
                'totalCalculations' => $totalCalculations,
                'totalSavedModels' => $totalSavedModels,
                'monthlyActiveUsers' => $monthlyActiveUsers,
                'newUsersThisMonth' => $newUsersThisMonth,
            ],
            'calculationsPerMonthLabels' => $monthLabelsDisplay,
            'calculationsPerMonthData' => $calculationsChartData,
            'registrationsPerMonthLabels' => $monthLabelsDisplay,
            'registrationsPerMonthData' => $registrationsChartData,
            'mostUsedModels' => $mostUsedModels,
            'riskDistribution' => $riskDistribution,
            'topProvinces' => $topProvinces,
            'topMunicipalities' => $topMunicipalities,
            'latestCalculations' => $latestCalculations,
            'latestRegisteredUsers' => $latestRegisteredUsers,
            'recentlySavedModels' => $recentlySavedModels,
        ]);
    }
}
