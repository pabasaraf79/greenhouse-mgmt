<?php

namespace App\Services;

use App\Models\Device;
use RuntimeException;

/**
 * Generates a ready-to-flash .ino for a device by substituting its WiFi/
 * server/API-key config into the canonical firmware template. The template
 * itself (firmware/greenhouse_node/greenhouse_node.ino) is the single source
 * of truth — there is no separate copy to keep in sync.
 */
class FirmwareGenerator
{
    private const TEMPLATE_PATH = 'firmware/greenhouse_node/greenhouse_node.ino';

    public function generate(Device $device): string
    {
        $path = base_path(self::TEMPLATE_PATH);

        if (! is_file($path)) {
            throw new RuntimeException("Firmware template not found at {$path}");
        }

        return strtr(file_get_contents($path), [
            '__WIFI_SSID__' => $device->wifi_ssid,
            '__WIFI_PASS__' => $device->wifi_password,
            '__SERVER__' => $device->server_url,
            '__DEVICE_KEY__' => $device->api_key,
        ]);
    }
}
