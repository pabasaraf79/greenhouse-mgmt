<?php

namespace App\Http\Controllers;

use App\Models\Greenhouse;
use App\Models\Threshold;
use Illuminate\Http\Request;

class GreenhouseController extends Controller
{
    /**
     * Default warning/critical bounds applied to every new greenhouse, so
     * the Thresholds page has something to edit immediately. Keyed by
     * parameter to match ThresholdController::PARAMETERS.
     */
    private const DEFAULT_THRESHOLDS = [
        'temperature' => ['warning_min' => 15, 'warning_max' => 35, 'critical_min' => 10, 'critical_max' => 40, 'unit' => '°C'],
        'humidity' => ['warning_min' => 40, 'warning_max' => 85, 'critical_min' => 30, 'critical_max' => 90, 'unit' => '%'],
        'soil_moisture' => ['warning_min' => 30, 'warning_max' => null, 'critical_min' => 20, 'critical_max' => null, 'unit' => '%'],
        'water_level_cm' => ['warning_min' => 25, 'warning_max' => null, 'critical_min' => 15, 'critical_max' => null, 'unit' => 'cm'],
        'gas_level' => ['warning_min' => null, 'warning_max' => 500, 'critical_min' => null, 'critical_max' => 600, 'unit' => 'ppm'],
    ];

    public function index()
    {
        $greenhouses = Greenhouse::withCount([
            'devices',
            'alerts as active_alerts_count' => fn ($q) => $q->where('status', 'active'),
        ])->orderBy('name')->get();

        return view('greenhouses.index', compact('greenhouses'));
    }

    public function create()
    {
        return view('greenhouses.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $greenhouse = Greenhouse::create($data);

        foreach (self::DEFAULT_THRESHOLDS as $parameter => $bounds) {
            Threshold::create(array_merge($bounds, [
                'greenhouse_id' => $greenhouse->id,
                'parameter' => $parameter,
            ]));
        }

        return redirect()->route('greenhouses.show', $greenhouse)
            ->with('status', 'Greenhouse created.');
    }

    public function show(Greenhouse $greenhouse)
    {
        $greenhouse->load(['devices', 'thresholds']);
        $deviceIds = $greenhouse->devices->pluck('id');

        $latestReading = \App\Models\SensorReading::whereIn('device_id', $deviceIds)
            ->latest('recorded_at')->first();

        $recentAlerts = $greenhouse->alerts()
            ->with('device')->latest('created_at')->take(10)->get();

        return view('greenhouses.show', compact('greenhouse', 'latestReading', 'recentAlerts'));
    }

    public function edit(Greenhouse $greenhouse)
    {
        return view('greenhouses.edit', compact('greenhouse'));
    }

    public function update(Request $request, Greenhouse $greenhouse)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $greenhouse->update($data);

        return redirect()->route('greenhouses.show', $greenhouse)
            ->with('status', 'Greenhouse updated.');
    }

    public function destroy(Greenhouse $greenhouse)
    {
        $greenhouse->delete();

        return redirect()->route('greenhouses.index')
            ->with('status', 'Greenhouse deleted.');
    }
}
