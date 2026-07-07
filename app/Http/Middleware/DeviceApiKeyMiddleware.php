<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceApiKeyMiddleware
{
    /**
     * Authenticate an ESP32 device by its X-Device-Key header.
     *
     * On success the authenticated Device is bound onto the request
     * (retrieve it with $request->attributes->get('device')) and the
     * device is marked online with a fresh last_seen_at timestamp and its
     * current source IP — this is what keeps ip_address accurate across
     * DHCP lease renewals without any manual re-entry.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Device-Key');

        $device = $key ? Device::where('api_key', $key)->first() : null;

        if (! $device) {
            return response()->json(['error' => 'Invalid device key'], 401);
        }

        $device->forceFill([
            'status' => 'online',
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
        ])->save();

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
