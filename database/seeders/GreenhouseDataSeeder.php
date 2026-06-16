<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Device;
use App\Models\FertigationSchedule;
use App\Models\Greenhouse;
use App\Models\Threshold;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GreenhouseDataSeeder extends Seeder
{
    public function run(): void
    {
        $greenhouses = [
            [
                'name' => 'GH-01 Main Hall',
                'location' => 'Block A',
                'description' => 'Primary production greenhouse, main hall.',
                'device' => [
                    'name' => 'GH-01 Controller',
                    'identifier' => 'ESP32-GH01',
                    'api_key' => 'gh01-secret-key-0001',
                    'ip_address' => '192.168.1.101',
                    'firmware_version' => '1.4.0',
                ],
            ],
            [
                'name' => 'GH-02 North Wing',
                'location' => 'Block B',
                'description' => 'Secondary greenhouse, north wing.',
                'device' => [
                    'name' => 'GH-02 Controller',
                    'identifier' => 'ESP32-GH02',
                    'api_key' => 'gh02-secret-key-0002',
                    'ip_address' => '192.168.1.102',
                    'firmware_version' => '1.4.0',
                ],
            ],
        ];

        foreach ($greenhouses as $data) {
            $greenhouse = Greenhouse::create([
                'name' => $data['name'],
                'location' => $data['location'],
                'description' => $data['description'],
            ]);

            $device = $greenhouse->devices()->create(array_merge($data['device'], [
                'status' => 'online',
                'last_seen_at' => now(),
            ]));

            $this->seedThresholds($greenhouse->id);
            $this->seedSensorReadings($device->id);
            $this->seedFertigationSchedules($greenhouse->id);
        }

        $this->seedAlerts();
    }

    /**
     * Default thresholds for a greenhouse.
     */
    protected function seedThresholds(int $greenhouseId): void
    {
        $thresholds = [
            [
                'parameter' => 'temperature',
                'warning_min' => 15, 'warning_max' => 35,
                'critical_min' => 10, 'critical_max' => 40,
                'unit' => '°C',
            ],
            [
                'parameter' => 'humidity',
                'warning_min' => 40, 'warning_max' => 85,
                'critical_min' => 30, 'critical_max' => 90,
                'unit' => '%',
            ],
            [
                'parameter' => 'soil_moisture',
                'warning_min' => 30, 'warning_max' => null,
                'critical_min' => 20, 'critical_max' => null,
                'unit' => '%',
            ],
            [
                'parameter' => 'water_level_cm',
                'warning_min' => 25, 'warning_max' => null,
                'critical_min' => 15, 'critical_max' => null,
                'unit' => 'cm',
            ],
        ];

        foreach ($thresholds as $t) {
            Threshold::create(array_merge($t, ['greenhouse_id' => $greenhouseId]));
        }
    }

    /**
     * 7 days of readings, every 5 minutes, with realistic diurnal variation.
     * Batch-inserted for performance (~2016 rows per device).
     */
    protected function seedSensorReadings(int $deviceId): void
    {
        $start = Carbon::now()->subDays(7)->startOfMinute();
        $end = Carbon::now();
        $now = now();

        $rows = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            // Diurnal cycle: warmest mid-afternoon, most humid pre-dawn.
            $hour = $cursor->hour + $cursor->minute / 60;
            $dayFactor = sin(($hour - 9) / 24 * 2 * M_PI); // peak ~15:00

            $temperature = round(27 + $dayFactor * 4 + $this->jitter(1.2), 1);          // 22-32
            $temperature = max(22, min(32, $temperature));

            $humidity = round(67 - $dayFactor * 9 + $this->jitter(3), 1);                // 55-80
            $humidity = max(55, min(80, $humidity));

            $soilMoisture = round(50 + sin($hour / 24 * 2 * M_PI) * 18 + $this->jitter(4), 1); // 25-75
            $soilMoisture = max(25, min(75, $soilMoisture));

            $waterLevel = (int) max(20, min(90, 70 - ($cursor->diffInHours($start) * 0.2) + $this->jitter(5)));
            $gasLevel = (int) max(200, min(600, 350 + $this->jitter(60)));
            $rain = mt_rand(0, 100) < 8 ? mt_rand(50, 3000) : 0; // occasional rain
            $motion = mt_rand(0, 100) < 5;

            $payload = [
                'temperature' => $temperature,
                'humidity' => $humidity,
                'soil_moisture' => $soilMoisture,
                'water_level_cm' => $waterLevel,
                'gas_level' => $gasLevel,
                'rain' => $rain,
                'motion' => $motion,
            ];

            $rows[] = [
                'device_id' => $deviceId,
                'temperature' => $temperature,
                'humidity' => $humidity,
                'soil_moisture' => $soilMoisture,
                'water_level_cm' => $waterLevel,
                'gas_level' => $gasLevel,
                'rain' => $rain,
                'motion' => $motion,
                'raw_payload' => json_encode($payload),
                'recorded_at' => $cursor->toDateTimeString(),
                'created_at' => $now->toDateTimeString(),
            ];

            $cursor->addMinutes(5);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('sensor_readings')->insert($chunk);
        }
    }

    protected function seedFertigationSchedules(int $greenhouseId): void
    {
        FertigationSchedule::create([
            'greenhouse_id' => $greenhouseId,
            'name' => 'Morning Fertigation',
            'days_of_week' => ['mon', 'wed', 'fri'],
            'start_time' => '06:30:00',
            'duration_seconds' => 600,
            'dose_seconds' => 45,
            'enabled' => true,
            'next_run_at' => Carbon::tomorrow()->setTime(6, 30),
        ]);

        FertigationSchedule::create([
            'greenhouse_id' => $greenhouseId,
            'name' => 'Evening Watering',
            'days_of_week' => ['tue', 'thu', 'sat', 'sun'],
            'start_time' => '18:00:00',
            'duration_seconds' => 480,
            'dose_seconds' => 0,
            'enabled' => true,
            'next_run_at' => Carbon::today()->setTime(18, 0),
        ]);
    }

    /**
     * 5 sample alerts across both greenhouses, mixed severities/statuses.
     */
    protected function seedAlerts(): void
    {
        $devices = Device::with('greenhouse')->get();
        $first = $devices->first();
        $second = $devices->last();

        $samples = [
            [
                'device' => $first, 'parameter' => 'temperature', 'severity' => 'critical',
                'value' => '41.2 °C', 'message' => 'Temperature exceeded critical maximum (40 °C).',
                'status' => 'active', 'resolved_at' => null,
                'created_at' => now()->subHours(2),
            ],
            [
                'device' => $first, 'parameter' => 'soil_moisture', 'severity' => 'warning',
                'value' => '28 %', 'message' => 'Soil moisture below warning minimum (30 %).',
                'status' => 'acknowledged', 'resolved_at' => null,
                'created_at' => now()->subHours(6),
            ],
            [
                'device' => $second, 'parameter' => 'humidity', 'severity' => 'warning',
                'value' => '87 %', 'message' => 'Humidity above warning maximum (85 %).',
                'status' => 'active', 'resolved_at' => null,
                'created_at' => now()->subHours(9),
            ],
            [
                'device' => $second, 'parameter' => 'water_level_cm', 'severity' => 'critical',
                'value' => '13 cm', 'message' => 'Water level below critical minimum (15 cm).',
                'status' => 'resolved', 'resolved_at' => now()->subHours(20),
                'created_at' => now()->subDays(1),
            ],
            [
                'device' => $first, 'parameter' => 'gas_level', 'severity' => 'warning',
                'value' => '612', 'message' => 'Gas level elevated above normal range.',
                'status' => 'resolved', 'resolved_at' => now()->subDays(2),
                'created_at' => now()->subDays(2)->subHours(1),
            ],
        ];

        foreach ($samples as $s) {
            $device = $s['device'];
            Alert::create([
                'greenhouse_id' => $device->greenhouse_id,
                'device_id' => $device->id,
                'parameter' => $s['parameter'],
                'severity' => $s['severity'],
                'value' => $s['value'],
                'message' => $s['message'],
                'status' => $s['status'],
                'resolved_at' => $s['resolved_at'],
                'created_at' => $s['created_at'],
                'updated_at' => $s['created_at'],
            ]);
        }
    }

    protected function jitter(float $magnitude): float
    {
        return (mt_rand(-1000, 1000) / 1000) * $magnitude;
    }
}
