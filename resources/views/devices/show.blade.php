@extends('layouts.app')

@section('title', $device->name)
@section('subtitle', $device->greenhouse->name ?? 'Device detail')

@section('content')
    @if (session('new_api_key'))
        <div class="banner banner-success mb-3">
            @include('partials.icon', ['name' => 'shield', 'size' => 20])
            <div>
                <div class="fw-bold">Save this key — it will not be shown again</div>
                <div class="mono mt-1" style="font-size:.95rem;">{{ session('new_api_key') }}</div>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="gh-card mb-3">
                <div class="section-title mb-3">Device Details</div>
                @foreach ([['Name', $device->name],['Greenhouse', $device->greenhouse->name ?? '—'],['Identifier', $device->identifier],['IP Address', $device->ip_address ?: '—'],['Firmware', $device->firmware_version ?: '—']] as [$lbl,$val])
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted-2">{{ $lbl }}</span><span class="fw-semibold">{{ $val }}</span>
                    </div>
                @endforeach
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted-2">Status</span>
                    <span class="fw-semibold"><span class="status-dot {{ $device->status }}"></span>{{ ucfirst($device->status) }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted-2">Last Seen</span><span class="fw-semibold">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</span>
                </div>
            </div>

            <div class="gh-card">
                <div class="section-title mb-2">API Key</div>
                <div class="field-block d-flex align-items-center justify-content-between mb-3">
                    <span class="mono" id="apiKeyMask" data-full="{{ $device->api_key }}">{{ substr($device->api_key, 0, 8) }}••••••••••••••••••••••••</span>
                    <button class="btn btn-soft btn-sm" type="button" onclick="revealKey()">Reveal</button>
                </div>
                <form method="POST" action="{{ route('devices.regenerate-key', $device) }}"
                      onsubmit="return confirm('Regenerate the API key? The device must be reconfigured.');">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm" type="submit">
                        @include('partials.icon', ['name' => 'refresh', 'size' => 14]) Regenerate
                    </button>
                </form>
            </div>

            <div class="gh-card mt-3">
                <div class="section-title mb-2">Firmware</div>
                @if ($device->wifi_ssid && $device->wifi_password && $device->server_url)
                    <p class="text-muted-2 small mb-3">
                        Ready-to-flash <code>.ino</code> for this device: WiFi
                        "{{ $device->wifi_ssid }}", server {{ $device->server_url }}.
                    </p>
                    <a href="{{ route('devices.firmware', $device) }}" class="btn btn-accent btn-sm">
                        @include('partials.icon', ['name' => 'download', 'size' => 14]) Download Firmware
                    </a>
                @else
                    <p class="text-muted-2 small mb-3">
                        Set WiFi SSID, WiFi Password, and Server Address in
                        <a href="{{ route('devices.edit', $device) }}">Edit</a> to enable firmware download.
                    </p>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="gh-card mb-3">
                <div class="section-title mb-3">Last 10 Sensor Readings</div>
                <div class="table-responsive">
                    <table class="gh-table">
                        <thead><tr><th>Time</th><th>Temp</th><th>Hum</th><th>Soil</th><th>Water</th></tr></thead>
                        <tbody>
                            @forelse ($readings as $r)
                                <tr>
                                    <td class="text-muted-2">{{ $r->recorded_at->format('M d H:i') }}</td>
                                    <td>{{ $r->temperature ?? '—' }}</td>
                                    <td>{{ $r->humidity ?? '—' }}</td>
                                    <td>{{ $r->soil_moisture ?? '—' }}</td>
                                    <td>{{ $r->water_level_cm ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted-2 text-center py-3">No readings.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="gh-card">
                <div class="section-title mb-3">Last 10 Actuator Commands</div>
                <div class="table-responsive">
                    <table class="gh-table">
                        <thead><tr><th>Time</th><th>Actuator</th><th>Command</th><th>Source</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($commands as $c)
                                <tr>
                                    <td class="text-muted-2">{{ $c->created_at->format('M d H:i') }}</td>
                                    <td class="text-capitalize">{{ str_replace('_', ' ', $c->actuator) }}</td>
                                    <td class="fw-semibold text-uppercase">{{ $c->command }}</td>
                                    <td><span class="pill-source pill-{{ $c->source }}">{{ ucfirst($c->source) }}</span></td>
                                    <td>@include('partials.badge', ['status' => $c->status])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted-2 text-center py-3">No commands.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function revealKey() {
        const el = document.getElementById('apiKeyMask');
        el.textContent = el.dataset.full;
    }
</script>
@endpush
