<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Greenhouse;
use App\Services\FirmwareGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::with('greenhouse')->orderBy('name')->get();

        return view('devices.index', compact('devices'));
    }

    public function create(Request $request)
    {
        $greenhouses = Greenhouse::orderBy('name')->get();
        $defaultServer = $request->getSchemeAndHttpHost();

        return view('devices.create', compact('greenhouses', 'defaultServer'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'greenhouse_id' => ['required', 'exists:greenhouses,id'],
            'identifier' => ['required', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'wifi_ssid' => ['nullable', 'string', 'max:255'],
            'wifi_password' => ['nullable', 'string', 'max:255'],
            'server_url' => ['nullable', 'string', 'max:255'],
        ]);

        $data['api_key'] = Str::random(32);
        $data['status'] = 'unknown';

        $device = Device::create($data);

        return redirect()->route('devices.show', $device)
            ->with('status', 'Device registered.')
            ->with('new_api_key', $device->api_key);
    }

    public function show(Device $device)
    {
        $device->load('greenhouse');
        $readings = $device->sensorReadings()->latest('recorded_at')->take(10)->get();
        $commands = $device->actuatorCommands()->latest('created_at')->take(10)->get();

        return view('devices.show', compact('device', 'readings', 'commands'));
    }

    public function edit(Request $request, Device $device)
    {
        $greenhouses = Greenhouse::orderBy('name')->get();
        $defaultServer = $request->getSchemeAndHttpHost();

        return view('devices.edit', compact('device', 'greenhouses', 'defaultServer'));
    }

    public function update(Request $request, Device $device)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'greenhouse_id' => ['required', 'exists:greenhouses,id'],
            'identifier' => ['required', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:online,offline,unknown'],
            'wifi_ssid' => ['nullable', 'string', 'max:255'],
            'wifi_password' => ['nullable', 'string', 'max:255'],
            'server_url' => ['nullable', 'string', 'max:255'],
        ]);

        $device->update($data);

        return redirect()->route('devices.show', $device)
            ->with('status', 'Device updated.');
    }

    public function destroy(Device $device)
    {
        $device->delete();

        return redirect()->route('devices.index')
            ->with('status', 'Device deleted.');
    }

    public function regenerateKey(Device $device)
    {
        $device->update(['api_key' => Str::random(32)]);

        return redirect()->route('devices.show', $device)
            ->with('status', 'API key regenerated.')
            ->with('new_api_key', $device->api_key);
    }

    public function downloadFirmware(Device $device, FirmwareGenerator $generator)
    {
        if (! $device->wifi_ssid || ! $device->wifi_password || ! $device->server_url) {
            return redirect()->route('devices.edit', $device)
                ->with('status', 'Set WiFi SSID, WiFi Password, and Server Address before downloading firmware.');
        }

        $filename = 'greenhouse_node_'.Str::slug($device->identifier).'.ino';

        return response()->streamDownload(
            fn () => print($generator->generate($device)),
            $filename,
            ['Content-Type' => 'text/x-c++src']
        );
    }
}
