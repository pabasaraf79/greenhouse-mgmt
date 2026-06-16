<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Greenhouse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $currentGreenhouse = $request->filled('greenhouse')
            ? Greenhouse::find($request->input('greenhouse'))
            : null;

        $base = Alert::with(['greenhouse', 'device']);
        if ($currentGreenhouse) {
            $base->where('greenhouse_id', $currentGreenhouse->id);
        }

        // Tab counts (respecting greenhouse filter, ignoring the tab filter itself).
        $counts = [
            'all' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'warning' => (clone $base)->where('severity', 'warning')->count(),
            'critical' => (clone $base)->where('severity', 'critical')->count(),
            'resolved' => (clone $base)->where('status', 'resolved')->count(),
        ];

        $filter = $request->input('filter', 'all');
        $query = clone $base;
        match ($filter) {
            'active' => $query->where('status', 'active'),
            'warning' => $query->where('severity', 'warning'),
            'critical' => $query->where('severity', 'critical'),
            'resolved' => $query->where('status', 'resolved'),
            default => null,
        };

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $alerts = $query->latest('created_at')->paginate(15)->withQueryString();

        // Compute the breached-threshold display ("< 20%", "> 35 °C") for each alert.
        $thresholds = \App\Models\Threshold::whereIn('greenhouse_id', $alerts->pluck('greenhouse_id')->unique())
            ->get()
            ->keyBy(fn ($t) => $t->greenhouse_id.':'.$t->parameter);

        $thresholdDisplays = [];
        foreach ($alerts as $alert) {
            $thresholdDisplays[$alert->id] = $this->thresholdDisplay($alert, $thresholds);
        }

        return view('alerts.index', compact('alerts', 'counts', 'filter', 'currentGreenhouse', 'thresholdDisplays'));
    }

    /**
     * Build a "< 20%" / "> 35 °C" style label for the threshold an alert breached.
     */
    private function thresholdDisplay(Alert $alert, $thresholds): string
    {
        $t = $thresholds->get($alert->greenhouse_id.':'.$alert->parameter);
        if (! $t) {
            return '—';
        }

        $unit = $t->unit ? ' '.$t->unit : '';
        $num = preg_match('/-?\d+(\.\d+)?/', (string) $alert->value, $m) ? (float) $m[0] : null;

        // Pick min/max bound by severity.
        $min = $alert->severity === 'critical' ? $t->critical_min : $t->warning_min;
        $max = $alert->severity === 'critical' ? $t->critical_max : $t->warning_max;

        // Decide direction from the value when possible, else from whichever bound exists.
        if ($num !== null && $min !== null && $num < $min) {
            return "< {$min}{$unit}";
        }
        if ($num !== null && $max !== null && $num > $max) {
            return "> {$max}{$unit}";
        }
        if ($min !== null) {
            return "< {$min}{$unit}";
        }
        if ($max !== null) {
            return "> {$max}{$unit}";
        }

        return '—';
    }

    public function acknowledge(Alert $alert)
    {
        $alert->update(['status' => 'acknowledged']);

        return back()->with('status', 'Alert acknowledged.');
    }

    public function resolve(Alert $alert)
    {
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('status', 'Alert resolved.');
    }
}
