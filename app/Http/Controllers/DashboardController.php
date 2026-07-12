<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Greenhouse;
use App\Models\SensorReading;
use App\Models\Threshold;
use App\Support\ThresholdEvaluator;
use App\Models\CropActivityRecord;
use App\Models\AgriculturalInput;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $currentGreenhouse = $request->filled('greenhouse')
            ? Greenhouse::find($request->input('greenhouse'))
            : Greenhouse::orderBy('name')->first();

        $deviceIds = $currentGreenhouse
            ? $currentGreenhouse->devices()->pluck('id')
            : collect();

        $latestReading = SensorReading::whereIn('device_id', $deviceIds)
            ->latest('recorded_at')
            ->first();

        $thresholds = $currentGreenhouse
            ? $currentGreenhouse->thresholds()->get()->keyBy('parameter')
            : collect();

        // 24h readings (used for charts AND trend deltas).
        $since = now()->subDay();
        $readings = SensorReading::whereIn('device_id', $deviceIds)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get(['temperature', 'humidity', 'soil_moisture', 'water_level_cm', 'recorded_at']);

        // Build the four headline stat cards (with a real 24h trend delta).
        $metrics = [];
        $cards = [
            ['key' => 'temperature',    'label' => 'Temperature',  'unit' => '°C', 'icon' => 'thermometer', 'tone' => 'tone-temp'],
            ['key' => 'humidity',       'label' => 'Humidity',     'unit' => '%',  'icon' => 'droplet',     'tone' => 'tone-hum'],
            ['key' => 'soil_moisture',  'label' => 'Soil Moisture','unit' => '%',  'icon' => 'leaf2',       'tone' => 'tone-soil'],
            ['key' => 'water_level_cm', 'label' => 'Water Level',  'unit' => 'cm', 'icon' => 'waves',       'tone' => 'tone-water'],
        ];
        foreach ($cards as $card) {
            $value = $latestReading?->{$card['key']};

            // Trend = latest value vs the earliest reading in the 24h window.
            $earliest = $readings->first()?->{$card['key']};
            $trendDelta = ($value !== null && $earliest !== null)
                ? round($value - $earliest, 1)
                : null;

            $metrics[] = array_merge($card, [
                'value' => $value,
                'status' => ThresholdEvaluator::status($value, $thresholds->get($card['key'])),
                'trend_delta' => $trendDelta,
            ]);
        }

        $byHour = $readings->groupBy(fn ($r) => $r->recorded_at->format('H:00'));
        $labels = $byHour->keys()->values();
        $chartData = [
            'labels' => $labels,
            'temperature' => $labels->map(fn ($h) => round($byHour[$h]->avg('temperature'), 1)),
            'humidity' => $labels->map(fn ($h) => round($byHour[$h]->avg('humidity'), 1)),
            'soil_moisture' => $labels->map(fn ($h) => round($byHour[$h]->avg('soil_moisture'), 1)),
            'water_level_cm' => $labels->map(fn ($h) => round($byHour[$h]->avg('water_level_cm'), 1)),
        ];

        $alertQuery = Alert::with(['greenhouse', 'device']);
        if ($currentGreenhouse) {
            $alertQuery->where('greenhouse_id', $currentGreenhouse->id);
        }
        $recentAlerts = $alertQuery->latest('created_at')->take(5)->get();
        $criticalCount = (clone $alertQuery)->where('severity', 'critical')
            ->where('status', 'active')->count();

        $cropActivities = CropActivityRecord::orderBy('date', 'desc')->get();
        $agriculturalInputs = AgriculturalInput::orderBy('purchase_date', 'desc')->get();

        return view('dashboard', compact(
            'currentGreenhouse', 'latestReading', 'metrics', 'chartData', 'recentAlerts', 'criticalCount',
            'cropActivities', 'agriculturalInputs'
        ));
    }
}
