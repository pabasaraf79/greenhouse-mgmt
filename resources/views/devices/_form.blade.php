<div class="row g-3">
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $device->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Greenhouse <span class="text-danger">*</span></label>
        <select name="greenhouse_id" class="form-select @error('greenhouse_id') is-invalid @enderror" required>
            <option value="">Select greenhouse…</option>
            @foreach ($greenhouses as $gh)
                <option value="{{ $gh->id }}" @selected(old('greenhouse_id', $device->greenhouse_id ?? '') == $gh->id)>{{ $gh->name }}</option>
            @endforeach
        </select>
        @error('greenhouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Identifier (MAC) <span class="text-danger">*</span></label>
        <input type="text" name="identifier" value="{{ old('identifier', $device->identifier ?? '') }}"
               class="form-control mono @error('identifier') is-invalid @enderror" placeholder="ESP32-XXXX" required>
        @error('identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Firmware Version</label>
        <input type="text" name="firmware_version" value="{{ old('firmware_version', $device->firmware_version ?? '') }}"
               class="form-control @error('firmware_version') is-invalid @enderror" placeholder="1.0.0">
        @error('firmware_version')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @isset($device)
        <div class="col-12 col-md-6">
            <label class="form-label fw-semibold">IP Address</label>
            <input type="text" name="ip_address" value="{{ old('ip_address', $device->ip_address ?? '') }}"
                   class="form-control mono @error('ip_address') is-invalid @enderror" placeholder="192.168.1.x">
            <div class="form-text">Auto-detected from device traffic. Only override for a manual/static setup.</div>
            @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
                @foreach (['online', 'offline', 'unknown'] as $st)
                    <option value="{{ $st }}" @selected(old('status', $device->status) === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
        </div>
    @endisset
</div>

<hr class="my-4">
<div class="section-title mb-1">Firmware Configuration</div>
<p class="text-muted-2 small mb-3">These values are baked into the downloadable firmware for this device.</p>
<div class="row g-3">
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">WiFi SSID</label>
        <input type="text" name="wifi_ssid" value="{{ old('wifi_ssid', $device->wifi_ssid ?? '') }}"
               class="form-control @error('wifi_ssid') is-invalid @enderror" placeholder="MyGreenhouseWiFi">
        @error('wifi_ssid')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">WiFi Password</label>
        <input type="text" name="wifi_password" value="{{ old('wifi_password', $device->wifi_password ?? '') }}"
               class="form-control mono @error('wifi_password') is-invalid @enderror">
        @error('wifi_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Server Address</label>
        <input type="text" name="server_url" id="server_url"
               value="{{ old('server_url', $device->server_url ?? $defaultServer ?? '') }}"
               class="form-control mono @error('server_url') is-invalid @enderror" placeholder="http://192.168.1.50:8000">
        <div class="form-text">
            The LAN address this device will call. Pre-filled from the address you're using to view this
            page — <strong>make sure it's a LAN IP, not <code>localhost</code>/<code>127.0.0.1</code></strong>,
            or the device won't be able to reach the server.
        </div>
        <div class="invalid-feedback d-block d-none" id="server_url_warning">
            This looks like a loopback address — the ESP32 won't be able to reach it. Browse to this app using
            this machine's LAN IP instead, and re-open this page.
        </div>
        @error('server_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const input = document.getElementById('server_url');
        const warning = document.getElementById('server_url_warning');
        if (!input || !warning) return;
        const check = () => {
            const isLoopback = /^https?:\/\/(localhost|127\.0\.0\.1)([:/]|$)/i.test(input.value.trim());
            warning.classList.toggle('d-none', !isLoopback);
        };
        input.addEventListener('input', check);
        check();
    })();
</script>
@endpush
