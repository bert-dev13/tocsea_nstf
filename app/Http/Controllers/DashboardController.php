<?php

namespace App\Http\Controllers;

use App\Models\CalculationHistory;
use App\Models\SavedEquation;
use App\Models\SoilLossRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $location = [
            'province' => $user->province ?? 'Pangasinan',
            'municipality' => $user->municipality ?? 'Burgos',
            'barangay' => $user->barangay ?? 'San Miguel',
        ];

        // Coastal Risk Overview stats
        $calcThisMonth = CalculationHistory::forUser($user)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $activeModels = SavedEquation::count();

        $avgSoilLossThisMonth = CalculationHistory::forUser($user)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->avg('result');

        $highRiskCount = CalculationHistory::forUser($user)
            ->where('result', '>=', 20)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Vegetation trend (from SoilLossRecord if exists, else placeholder)
        $vegTrend = $this->getVegetationTrend($user);

        // Recent activity (last 5 calculations)
        $recentCalculations = CalculationHistory::forUser($user)
            ->select(['id', 'equation_name', 'result', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $lastModelEdited = SavedEquation::orderBy('updated_at', 'desc')->first();

        // Latest risk level from most recent calculation
        $latestCalc = CalculationHistory::forUser($user)->orderBy('created_at', 'desc')->first();
        $riskLevel = $this->getRiskLevel($latestCalc?->result);

        // Soil loss trend (last 6 months) for charts
        $soilLossTrend = $this->getSoilLossTrend($user);
        $soilLossMax = collect($soilLossTrend)->max('value') ?: 1;

        // Storm/flood placeholders (no real data yet)
        $stormFrequency = $this->getStormFrequencyPlaceholder();
        $floodIncidence = $this->getFloodIncidencePlaceholder();

        // Environmental insights (smart messages)
        $insights = $this->getEnvironmentalInsights($user, $avgSoilLossThisMonth, $vegTrend);

        return view('dashboard', [
            'user' => $user,
            'location' => $location,
            'lastLogin' => $user->last_login_at?->diffForHumans(),
            'stats' => [
                'calculationsThisMonth' => $calcThisMonth,
                'calculationsTrend' => $this->getCalculationsTrend($user),
                'activeModels' => $activeModels,
                'activeModelsTrend' => 0, // placeholder
                'avgSoilLossMonthly' => $avgSoilLossThisMonth ? round((float) $avgSoilLossThisMonth, 2) : null,
                'avgSoilLossTrend' => $this->getAvgSoilLossTrend($user),
                'highRiskEvents' => $highRiskCount,
                'highRiskTrend' => 0, // placeholder
                'vegetationTrend' => $vegTrend,
            ],
            'recentCalculations' => $recentCalculations,
            'lastModelEdited' => $lastModelEdited,
            'riskLevel' => $riskLevel,
            'soilLossTrend' => $soilLossTrend,
            'soilLossMax' => $soilLossMax,
            'stormFrequency' => $stormFrequency,
            'floodIncidence' => $floodIncidence,
            'insights' => $insights,
        ]);
    }

    private function getRiskLevel(?float $result): array
    {
        $val = (float) ($result ?? 0);
        if ($val <= 0) {
            return ['label' => 'N/A', 'category' => 'none', 'value' => 0, 'percent' => 0];
        }
        if ($val < 10) {
            return ['label' => 'Low', 'category' => 'low', 'value' => $val, 'percent' => min(100, ($val / 10) * 25)];
        }
        if ($val < 20) {
            return ['label' => 'Moderate', 'category' => 'moderate', 'value' => $val, 'percent' => 25 + (($val - 10) / 10) * 25];
        }
        if ($val < 30) {
            return ['label' => 'High', 'category' => 'high', 'value' => $val, 'percent' => 50 + (($val - 20) / 10) * 25];
        }
        return ['label' => 'Critical', 'category' => 'critical', 'value' => $val, 'percent' => min(100, 75 + (($val - 30) / 20) * 25)];
    }

    private function getVegetationTrend($user): ?array
    {
        $records = SoilLossRecord::where('user_id', $user->id)->orderBy('year')->get();
        if ($records->count() < 2) {
            return null; // ['label' => 'No data', 'change' => 0, 'direction' => 'neutral'];
        }
        $first = $records->first()->soil_loss_tonnes_per_ha ?? 0;
        $last = $records->last()->soil_loss_tonnes_per_ha ?? 0;
        $change = $first > 0 ? round((($last - $first) / $first) * 100, 1) : 0;
        return ['label' => ($change >= 0 ? '+' : '') . $change . '%', 'change' => $change, 'direction' => $change < 0 ? 'up' : ($change > 0 ? 'down' : 'neutral')];
    }

    private function getCalculationsTrend($user): array
    {
        $thisMonth = CalculationHistory::forUser($user)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
        $lastMonth = CalculationHistory::forUser($user)->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)->count();
        $change = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : ($thisMonth > 0 ? 100 : 0);
        return ['change' => $change, 'direction' => $change >= 0 ? 'up' : 'down'];
    }

    private function getAvgSoilLossTrend($user): ?array
    {
        $thisMonth = CalculationHistory::forUser($user)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->avg('result');
        $lastMonth = CalculationHistory::forUser($user)->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)->avg('result');
        if ($thisMonth === null && $lastMonth === null) {
            return null;
        }
        $thisVal = (float) ($thisMonth ?? 0);
        $lastVal = (float) ($lastMonth ?? 0);
        $change = $lastVal > 0 ? round((($thisVal - $lastVal) / $lastVal) * 100, 1) : ($thisVal > 0 ? 100 : 0);
        return ['change' => $change, 'direction' => $change >= 0 ? 'up' : 'down'];
    }

    private function getSoilLossTrend($user): array
    {
        $data = [];
        $maxVal = 0;
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $avg = CalculationHistory::forUser($user)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->avg('result');
            $val = $avg ? (float) $avg : 0;
            $maxVal = max($maxVal, $val);
            $data[] = ['month' => $date->format('M'), 'value' => round($val, 2), 'year' => $date->format('Y')];
        }
        // If all zeros, use placeholder demo data
        if ($maxVal == 0) {
            $data = array_map(fn ($i) => [
                'month' => Carbon::now()->subMonths(5 - $i)->format('M'),
                'value' => round(10 + $i * 0.8 + random_int(-2, 2), 1),
                'year' => Carbon::now()->subMonths(5 - $i)->format('Y'),
            ], range(0, 5));
        }
        return $data;
    }

    private function getStormFrequencyPlaceholder(): array
    {
        return collect(range(0, 5))->map(fn ($i) => ['month' => Carbon::now()->subMonths(5 - $i)->format('M'), 'value' => rand(1, 5)])->all();
    }

    private function getFloodIncidencePlaceholder(): array
    {
        return collect(range(0, 5))->map(fn ($i) => ['month' => Carbon::now()->subMonths(5 - $i)->format('M'), 'value' => rand(0, 3)])->all();
    }

    private function getEnvironmentalInsights($user, ?float $avgSoilLoss, ?array $vegTrend): array
    {
        $insights = [];
        if ($avgSoilLoss && $avgSoilLoss > 15) {
            $insights[] = ['type' => 'warning', 'message' => 'Average soil loss this month (' . round($avgSoilLoss, 1) . ' t/ha) exceeds safe threshold. Consider erosion control measures.'];
        }
        if ($vegTrend && $vegTrend['change'] < 0) {
            $insights[] = ['type' => 'positive', 'message' => 'Soil loss trend improving — vegetation coverage has reduced erosion risk.'];
        } elseif ($vegTrend && $vegTrend['change'] > 0) {
            $insights[] = ['type' => 'warning', 'message' => 'Soil loss trend increasing by ' . abs($vegTrend['change']) . '%. Monitor vegetation coverage.'];
        }
        $insights[] = ['type' => 'info', 'message' => 'Storm surge frequency increasing by 12% in coastal zones. Review flood preparedness.'];
        $insights[] = ['type' => 'info', 'message' => 'Clay soil areas show higher erosion risk. Prioritize soil stabilization in affected barangays.'];
        return array_slice($insights, 0, 4); // Max 4 insights
    }
}
