<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherController extends Controller
{
    /**
     * Fetch weather data for the authenticated user's registered location.
     * Proxies OpenWeatherMap API to keep the API key server-side.
     */
    public function index(Request $request): JsonResponse
    {
        $apiKey = config('services.openweathermap.key') ?: env('OPENWEATHERMAP_API_KEY');
        if (empty($apiKey)) {
            return response()->json([
                'error' => 'Weather service is not configured. Please set OPENWEATHERMAP_API_KEY in .env',
            ], 503);
        }

        $user = $request->user();
        $locationQuery = implode(', ', array_filter([
            $user->municipality,
            $user->province,
            'Philippines',
        ]));

        if (empty(trim(str_replace(',', '', $locationQuery)))) {
            return response()->json([
                'error' => 'User location is not set.',
            ], 400);
        }

        try {
            // Geocode: location name → lat, lon
            $geoRes = Http::timeout(10)->get('https://api.openweathermap.org/geo/1.0/direct', [
                'q' => $locationQuery,
                'limit' => 1,
                'appid' => $apiKey,
            ]);

            if (!$geoRes->successful() || empty($geoRes->json())) {
                Log::warning('OpenWeatherMap geocoding failed', [
                    'query' => $locationQuery,
                    'status' => $geoRes->status(),
                ]);
                return response()->json([
                    'error' => 'Could not find coordinates for your location.',
                    'location' => $locationQuery,
                ], 404);
            }

            $geo = $geoRes->json()[0];
            $lat = $geo['lat'];
            $lon = $geo['lon'];
            $displayName = $geo['name'] ?? $user->municipality;

            // Fetch current weather and 5-day forecast in parallel
            $units = 'metric';
            $baseParams = ['lat' => $lat, 'lon' => $lon, 'appid' => $apiKey, 'units' => $units];

            $currentRes = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', $baseParams);
            $forecastRes = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/forecast', array_merge($baseParams, ['cnt' => 40]));

            if (!$currentRes->successful()) {
                return response()->json([
                    'error' => 'Could not fetch current weather.',
                    'lat' => $lat,
                    'lon' => $lon,
                ], $currentRes->status());
            }

            $current = $currentRes->json();

            // Build 5-day daily forecast (one entry per day from 3-hour intervals)
            $dailyForecasts = [];
            if ($forecastRes->successful()) {
                $forecastList = $forecastRes->json()['list'] ?? [];
                $grouped = [];
                foreach ($forecastList as $item) {
                    $date = date('Y-m-d', $item['dt']);
                    if (!isset($grouped[$date])) {
                        $grouped[$date] = [];
                    }
                    $grouped[$date][] = $item;
                }
                $count = 0;
                foreach ($grouped as $date => $items) {
                    if ($count >= 5) break;
                    $tempsMin = array_map(fn ($x) => $x['main']['temp_min'] ?? $x['main']['temp'], $items);
                    $tempsMax = array_map(fn ($x) => $x['main']['temp_max'] ?? $x['main']['temp'], $items);
                    $pops = array_map(fn ($x) => $x['pop'] ?? 0, $items);
                    $midIdx = (int) floor(count($items) / 2);
                    $midWeather = $items[$midIdx]['weather'][0] ?? ['id' => 800, 'main' => 'Clear', 'description' => '', 'icon' => '01d'];
                    $dailyForecasts[] = [
                        'date' => $date,
                        'day_name' => date('D', strtotime($date)),
                        'day_full' => date('l', strtotime($date)),
                        'temp_min' => (int) round(min($tempsMin)),
                        'temp_max' => (int) round(max($tempsMax)),
                        'pop' => (int) round(max($pops) * 100),
                        'condition' => [
                            'id' => $midWeather['id'] ?? 800,
                            'main' => $midWeather['main'] ?? 'Clear',
                            'description' => $midWeather['description'] ?? '',
                            'icon' => $midWeather['icon'] ?? '01d',
                        ],
                    ];
                    $count++;
                }
            }

            return response()->json([
                'updated_at' => now()->toIso8601String(),
                'location' => [
                    'display' => $displayName,
                    'query' => $locationQuery,
                ],
                'current' => [
                    'temp' => round($current['main']['temp'] ?? 0, 1),
                    'feels_like' => round($current['main']['feels_like'] ?? 0, 1),
                    'humidity' => $current['main']['humidity'] ?? 0,
                    'pressure' => $current['main']['pressure'] ?? 0,
                    'wind_speed' => round(($current['wind']['speed'] ?? 0) * 3.6, 1),
                    'sunrise' => $current['sys']['sunrise'] ?? null,
                    'sunset' => $current['sys']['sunset'] ?? null,
                    'condition' => [
                        'id' => $current['weather'][0]['id'] ?? 800,
                        'main' => $current['weather'][0]['main'] ?? 'Clear',
                        'description' => $current['weather'][0]['description'] ?? '',
                        'icon' => $current['weather'][0]['icon'] ?? '01d',
                    ],
                ],
                'forecast' => array_slice($dailyForecasts, 0, 5),
            ]);
        } catch (\Throwable $e) {
            Log::error('Weather API error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'An error occurred while fetching weather data.',
            ], 500);
        }
    }
}
