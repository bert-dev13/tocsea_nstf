<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalculationHistory;
use App\Models\SavedEquation;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = [
            'totalUsers' => User::count(),
            'adminUsers' => User::where('is_admin', true)->count(),
            'totalCalculations' => CalculationHistory::count(),
            'calculationsThisMonth' => CalculationHistory::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'savedModels' => SavedEquation::count(),
            'newUsersThisMonth' => User::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        $recentUsers = User::orderByDesc('created_at')->limit(5)->get();

        return view('admin.dashboard.index', [
            'user' => $user,
            'stats' => $stats,
            'recentUsers' => $recentUsers,
        ]);
    }
}
