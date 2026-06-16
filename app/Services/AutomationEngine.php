<?php

namespace App\Services;

use App\Models\ActuatorCommand;
use App\Models\Device;
use App\Models\SensorReading;

/**
 * Real rule engine: on each incoming reading, compares sensor values to the
 * greenhouse thresholds and drives actuators automatically.
 *
 * Idempotent — it only issues a command when the actuator isn't already in the
 * desired state, and it only auto-turns-OFF actuators that automation itself
 * turned on (so it never overrides a manual override).
 */
class AutomationEngine
{
    public function __construct(private CommandIssuer $issuer)
    {
    }

    /**
     * Sensor-driven rules. Single source of truth — the Control Panel reads
     * this same list so the displayed rules match what actually runs.
     *
     * `limit` falls back to this default when the greenhouse has no threshold.
     */
    public static function rules(): array
    {
        return [
            ['key' => 'low_moisture',   'name' => 'Low Moisture',   'parameter' => 'soil_moisture',  'comparator' => 'below', 'actuator' => 'pump',   'default' => 30, 'condition' => 'Soil moisture < 30%', 'action' => 'Irrigation Pump → ON'],
            ['key' => 'high_temp_vent', 'name' => 'High Temp Vent', 'parameter' => 'temperature',    'comparator' => 'above', 'actuator' => 'fan',    'default' => 35, 'condition' => 'Temperature > 35 °C', 'action' => 'Ventilation Fan → ON'],
            ['key' => 'low_water',      'name' => 'Low Water',      'parameter' => 'water_level_cm', 'comparator' => 'below', 'actuator' => 'valve1', 'default' => 25, 'condition' => 'Water level < 25 cm', 'action' => 'Fill Valve → OPEN'],
        ];
    }

    /**
     * Evaluate a reading and issue any needed actuator commands.
     *
     * @return int Number of automatic commands issued.
     */
    public function evaluate(SensorReading $reading, Device $device): int
    {
        $greenhouse = $device->greenhouse;
        if (! $greenhouse) {
            return 0;
        }

        $thresholds = $greenhouse->thresholds()->get()->keyBy('parameter');
        $ruleStates = \App\Models\AutomationRule::states();
        $actions = 0;

        foreach (self::rules() as $rule) {
            // Skip rules an operator has disabled (default enabled if no row yet).
            if (($ruleStates[$rule['key']] ?? true) === false) {
                continue;
            }

            $value = $reading->{$rule['parameter']};
            if ($value === null) {
                continue;
            }

            $threshold = $thresholds->get($rule['parameter']);
            $limit = $rule['comparator'] === 'below'
                ? ($threshold->warning_min ?? $rule['default'])
                : ($threshold->warning_max ?? $rule['default']);

            $conditionActive = $rule['comparator'] === 'below'
                ? ((float) $value < $limit)
                : ((float) $value > $limit);

            $last = ActuatorCommand::where('device_id', $device->id)
                ->where('actuator', $rule['actuator'])
                ->latest('id')
                ->first();
            $isOn = $last && $last->command === 'on';

            if ($conditionActive && ! $isOn) {
                // Turn the actuator ON to respond to the breach.
                $this->issuer->issue($device, $rule['actuator'], 'on', null, 'auto');
                $actions++;
            } elseif (! $conditionActive && $isOn && $last->source === 'auto') {
                // Condition cleared — automation cleans up only what it started.
                $this->issuer->issue($device, $rule['actuator'], 'off', null, 'auto');
                $actions++;
            }
        }

        return $actions;
    }
}
