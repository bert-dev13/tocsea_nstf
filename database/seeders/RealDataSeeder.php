<?php

namespace Database\Seeders;

use App\Models\CalculationHistory;
use App\Models\SavedEquation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RealDataSeeder extends Seeder
{
    /**
     * Philippine locations (real provinces, municipalities, barangays) for realistic records.
     */
    private function locationSets(): array
    {
        return [
            ['province' => 'Cagayan', 'municipality' => 'Tuguegarao City', 'barangay' => 'Centro 1'],
            ['province' => 'Cagayan', 'municipality' => 'Lal-lo', 'barangay' => 'Magapit'],
            ['province' => 'Isabela', 'municipality' => 'Santiago City', 'barangay' => 'Villa Gonzaga'],
            ['province' => 'Isabela', 'municipality' => 'Cauayan', 'barangay' => 'Turayong'],
            ['province' => 'Pangasinan', 'municipality' => 'Burgos', 'barangay' => 'San Miguel'],
            ['province' => 'Pangasinan', 'municipality' => 'Lingayen', 'barangay' => 'Poblacion'],
            ['province' => 'Nueva Ecija', 'municipality' => 'Cabanatuan City', 'barangay' => 'Sumacab Este'],
            ['province' => 'Nueva Vizcaya', 'municipality' => 'Bambang', 'barangay' => 'Poblacion'],
            ['province' => 'Quirino', 'municipality' => 'Cabarroguis', 'barangay' => 'Poblacion'],
            ['province' => 'Bataan', 'municipality' => 'Balanga City', 'barangay' => 'Poblacion'],
            ['province' => 'Tarlac', 'municipality' => 'Tarlac City', 'barangay' => 'San Roque'],
            ['province' => 'Pampanga', 'municipality' => 'Angeles City', 'barangay' => 'Balibago'],
            ['province' => 'Bulacan', 'municipality' => 'Malolos', 'barangay' => 'Atlag'],
            ['province' => 'Quezon', 'municipality' => 'Lucena City', 'barangay' => 'Isabang'],
            ['province' => 'Camarines Sur', 'municipality' => 'Naga City', 'barangay' => 'Carolina'],
            ['province' => 'Albay', 'municipality' => 'Legazpi City', 'barangay' => 'Bitano'],
            ['province' => 'Iloilo', 'municipality' => 'Iloilo City', 'barangay' => 'Molo'],
            ['province' => 'Negros Occidental', 'municipality' => 'Bacolod City', 'barangay' => 'Villamonte'],
            ['province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Talamban'],
            ['province' => 'Davao del Sur', 'municipality' => 'Davao City', 'barangay' => 'Talomo'],
        ];
    }

    /**
     * Realistic equation names (unique) and formula snippets.
     */
    private function equationTemplates(): array
    {
        return [
            ['name' => 'RUSLE Cagayan Valley 2024', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Soil Erosion Model Isabela', 'formula' => 'y = 0.45 + 1.2*R + 0.8*K'],
            ['name' => 'Pangasinan Coastal Erosion', 'formula' => 'E = a + b*SLOPE + c*RAIN'],
            ['name' => 'Nueva Ecija RUSLE Adjusted', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Buguey Soil Loss Regression', 'formula' => 'y = 2.1 + 0.05*R_factor'],
            ['name' => 'Cagayan River Basin Model', 'formula' => 'Loss = f(precip, slope, cover)'],
            ['name' => 'Santiago City Erosion Index', 'formula' => 'EI = R * K * LS'],
            ['name' => 'Lal-lo Agricultural Loss', 'formula' => 'A = R*K*LS*C*P'],
            ['name' => 'Tuguegarao Urban Edge', 'formula' => 'y = intercept + coef*X'],
            ['name' => 'Cauayan Cropland Model', 'formula' => 'Soil_Loss = R*K*LS*C'],
            ['name' => 'Burgos Pangasinan RUSLE', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Lingayen Gulf Coastal', 'formula' => 'E = a + b*seawall + c*storm'],
            ['name' => 'Cabanatuan Upland', 'formula' => 'y = 1.2 + 0.04*precipitation'],
            ['name' => 'Bambang Nueva Vizcaya', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Quirino Forest Margin', 'formula' => 'Loss = f(R,K,LS,C,P)'],
            ['name' => 'Bataan Peninsula Model', 'formula' => 'E = R*K*LS*C'],
            ['name' => 'Tarlac Central Luzon', 'formula' => 'y = b0 + b1*x1 + b2*x2'],
            ['name' => 'Pampanga Delta Erosion', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Malolos Bulacan RUSLE', 'formula' => 'Soil_Loss = R*K*LS*C*P'],
            ['name' => 'Lucena Quezon Coastal', 'formula' => 'E = a + b*floods + c*storm'],
            ['name' => 'Naga Camarines Sur', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Legazpi Albay Volcanic', 'formula' => 'y = f(ash, slope, rain)'],
            ['name' => 'Iloilo Western Visayas', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Bacolod Negros Model', 'formula' => 'E = R*K*LS*C'],
            ['name' => 'Cebu Central Visayas', 'formula' => 'Soil_Loss = R*K*LS*C*P'],
            ['name' => 'Davao Talomo Basin', 'formula' => 'y = intercept + coef*precip'],
            ['name' => 'Central Luzon RUSLE', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Cagayan Valley Default', 'formula' => 'y = 0.45 + 1.2*R + 0.8*K'],
            ['name' => 'Isabela Upland Erosion', 'formula' => 'Loss = R*K*LS*C'],
            ['name' => 'Pangasinan Lowland', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Nueva Ecija Irrigated', 'formula' => 'E = a + b*precipitation'],
            ['name' => 'Bambang Watershed', 'formula' => 'y = b0 + b1*R + b2*K'],
            ['name' => 'Cabarroguis Quirino', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Balanga Bataan Coastal', 'formula' => 'E = f(seawall, storm, floods)'],
            ['name' => 'Tarlac City Upland', 'formula' => 'Soil_Loss = R*K*LS*C*P'],
            ['name' => 'Angeles Pampanga', 'formula' => 'y = 1.0 + 0.05*R_factor'],
            ['name' => 'Malolos Delta Model', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Lucena Quezon Upland', 'formula' => 'E = R*K*LS*C'],
            ['name' => 'Naga Bicol RUSLE', 'formula' => 'Loss = R*K*LS*C*P'],
            ['name' => 'Legazpi Mayon Slope', 'formula' => 'y = f(slope, rain, cover)'],
            ['name' => 'Iloilo Panay Model', 'formula' => 'A = R * K * LS * C * P'],
            ['name' => 'Bacolod Sugarcane', 'formula' => 'E = a + b*precip + c*storm'],
        ];
    }

    public function run(): void
    {
        $locations = $this->locationSets();
        $equationTemplates = $this->equationTemplates();

        // 20 real-looking user accounts (avoid conflicting with existing admin/user)
        $existingEmails = User::pluck('email')->map(fn ($e) => strtolower($e))->flip()->all();
        $names = [
            'Maria Santos', 'Juan Dela Cruz', 'Rosa Reyes', 'Carlos Mendoza', 'Elena Bautista',
            'Roberto Garcia', 'Ana Fernandez', 'Jose Ramirez', 'Carmen Torres', 'Antonio Lopez',
            'Teresa Cruz', 'Miguel Santiago', 'Lourdes Morales', 'Pedro Reyes', 'Sofia Gutierrez',
            'Francisco Ramos', 'Isabel Villanueva', 'Ricardo Castro', 'Rosa Navarro', 'Fernando Ortiz',
        ];

        $users = [];
        for ($i = 0; $i < 20; $i++) {
            $loc = $locations[$i % count($locations)];
            $baseName = str_replace(' ', '.', strtolower($names[$i]));
            $email = $baseName . '@gmail.com';
            $attempt = 0;
            while (isset($existingEmails[strtolower($email)])) {
                $attempt++;
                $email = $baseName . $attempt . '@yahoo.com';
            }
            $existingEmails[strtolower($email)] = true;

            $users[] = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $names[$i],
                    'password' => Hash::make('password'),
                    'province' => $loc['province'],
                    'municipality' => $loc['municipality'],
                    'barangay' => $loc['barangay'],
                    'is_admin' => false,
                    'role' => 'user',
                    'is_disabled' => false,
                ]
            );
        }

        // 40 saved equations (unique equation_name), spread across users
        $equations = [];
        foreach (array_slice($equationTemplates, 0, 40) as $idx => $tpl) {
            $owner = $users[$idx % count($users)];
            $equations[] = SavedEquation::firstOrCreate(
                ['equation_name' => $tpl['name']],
                [
                    'user_id' => $owner->id,
                    'formula' => $tpl['formula'],
                    'location' => $owner->municipality . ', ' . $owner->province,
                    'notes' => $idx % 3 === 0 ? 'Field-validated 2024.' : null,
                ]
            );
        }

        // 40 calculation histories: realistic inputs and results, spread across users
        $soilTypes = ['Clay', 'Loam', 'Sandy', 'Silty', 'Clay Loam'];
        for ($i = 0; $i < 40; $i++) {
            $user = $users[$i % count($users)];
            $eq = $equations[$i % count($equations)];
            $result = (float) rand(1, 120) / 10; // 0.1 to 12.0, some higher for variety
            if ($i % 5 === 0) {
                $result = (float) rand(50, 180) / 10; // 5 to 18 for moderate/high
            }

            CalculationHistory::create([
                'user_id' => $user->id,
                'saved_equation_id' => $eq->id,
                'equation_name' => $eq->equation_name,
                'formula_snapshot' => $eq->formula,
                'inputs' => [
                    'precipitation' => round(rand(1800, 3200) / 10, 1),
                    'slope' => round(rand(2, 25), 1),
                    'soil_type' => $soilTypes[array_rand($soilTypes)],
                    'cover_factor' => round(rand(1, 95) / 100, 2),
                    'tropical_storm' => rand(0, 8),
                    'floods' => rand(0, 5),
                ],
                'result' => $result,
                'notes' => $i % 4 === 0 ? 'Field check pending.' : null,
            ]);
        }
    }
}
