<?php

namespace Database\Seeders;

use App\Models\RegressionModel;
use App\Models\SoilLossRecord;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) return;

        // Demo soil loss records (historical)
        $years = [2020, 2021, 2022, 2023, 2024];
        $losses = [12.5, 13.2, 14.1, 13.8, 14.5];
        foreach ($years as $i => $year) {
            SoilLossRecord::firstOrCreate(
                ['user_id' => $user->id, 'year' => $year],
                [
                    'province' => $user->province ?? 'Pangasinan',
                    'municipality' => $user->municipality ?? 'Burgos',
                    'barangay' => $user->barangay ?? 'San Miguel',
                    'soil_loss_tonnes_per_ha' => $losses[$i],
                    'risk_level' => $losses[$i] > 14 ? 'high' : ($losses[$i] > 12 ? 'medium' : 'low'),
                    'model_used' => 'Buguey',
                ]
            );
        }

        // Demo regression model
        RegressionModel::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Buguey Default'],
            [
                'type' => 'buguey',
                'equation_params' => ['R' => 0.85, 'K' => 0.32, 'LS' => 1.2, 'C' => 0.15, 'P' => 0.8],
                'location' => $user->municipality,
                'is_default' => true,
            ]
        );

        // Demo notifications
        if (UserNotification::where('user_id', $user->id)->count() === 0) {
            UserNotification::create([
                'user_id' => $user->id,
                'type' => 'weather',
                'title' => 'Moderate rain expected',
                'message' => '24–48h forecast shows scattered showers. Plan fieldwork accordingly.',
                'severity' => 'info',
            ]);
            UserNotification::create([
                'user_id' => $user->id,
                'type' => 'erosion',
                'title' => 'Soil loss trend alert',
                'message' => 'Annual soil loss in your area has increased 2% vs last year.',
                'severity' => 'warning',
            ]);
        }
    }
}
