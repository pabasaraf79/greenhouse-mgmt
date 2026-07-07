<?php

namespace App\Services;

use App\Models\FertigationSchedule;
use Carbon\Carbon;

/**
 * Executes a fertigation schedule for real: issues the irrigation-pump and
 * dosing-valve commands (with durations so the device auto-stops), stamps
 * last_run_at, and recomputes next_run_at.
 *
 * Dosing drives valve2 — the real hardware has no dedicated fertiliser pump
 * relay, so valve2 doubles as the dosing valve (see ControlController).
 */
class ScheduleRunner
{
    private const DAY_MAP = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

    public function __construct(private CommandIssuer $issuer)
    {
    }

    /**
     * Run a schedule now.
     *
     * @return bool True if commands were dispatched to a device.
     */
    public function run(FertigationSchedule $schedule, ?int $userId = null): bool
    {
        $device = $schedule->greenhouse?->devices()->first();

        if ($device) {
            if ($schedule->duration_seconds > 0) {
                $this->issuer->issue($device, 'pump', 'on', $schedule->duration_seconds, 'schedule', $userId);
            }
            if ($schedule->dose_seconds > 0) {
                $this->issuer->issue($device, 'valve2', 'on', $schedule->dose_seconds, 'schedule', $userId);
            }
        }

        $schedule->update([
            'last_run_at' => now(),
            'next_run_at' => $this->nextRun($schedule),
        ]);

        return $device !== null;
    }

    /**
     * Compute the next datetime this schedule should fire, based on its
     * days_of_week + start_time. Returns null if no days are selected.
     */
    public function nextRun(FertigationSchedule $schedule): ?Carbon
    {
        $days = array_filter(array_map(
            fn ($d) => self::DAY_MAP[$d] ?? null,
            (array) $schedule->days_of_week
        ), fn ($x) => $x !== null);

        if (empty($days)) {
            return null;
        }

        [$h, $m] = array_pad(explode(':', (string) $schedule->start_time), 2, '0');

        // Look ahead up to 7 days for the next matching weekday at start_time.
        for ($i = 0; $i <= 7; $i++) {
            $candidate = now()->copy()->addDays($i)->setTime((int) $h, (int) $m, 0);
            if (in_array($candidate->dayOfWeek, $days, true) && $candidate->greaterThan(now())) {
                return $candidate;
            }
        }

        return null;
    }
}
