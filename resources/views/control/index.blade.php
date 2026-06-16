@extends('layouts.app')

@section('title', 'Control Panel')
@section('subtitle', 'Manual overrides & automation')

@section('content')
    {{-- Offline banner --}}
    @if ($offlineDevices->count() > 0)
        <div class="banner banner-warning mb-3">
            @include('partials.icon', ['name' => 'warning', 'size' => 22])
            <div class="flex-fill">
                <div class="fw-bold">{{ $offlineDevices->count() }} device{{ $offlineDevices->count() > 1 ? 's' : '' }} offline</div>
                <div style="font-size:.85rem;">{{ $offlineDevices->pluck('name')->join(', ') }} — automation rules depending on them are paused.</div>
            </div>
            <a href="{{ route('devices.index') }}" class="btn btn-soft btn-sm">Inspect</a>
        </div>
    @endif

    {{-- Manual control --}}
    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <div class="section-title">Manual Control</div>
            <div class="section-sub">Override relays directly — changes log against your account</div>
        </div>
        <span class="status-online-pill" id="relayCounter">
            @include('partials.icon', ['name' => 'shield', 'size' => 15]) {{ $activeCount }} of 4 relays active
        </span>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($actuators as $a)
            @php $latest = $a['latest']; @endphp
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="gh-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-icon tone-green">@include('partials.icon', ['name' => $a['icon'], 'size' => 22])</span>
                        <div class="form-check form-switch switch-lg m-0">
                            <input class="form-check-input actuator-toggle" type="checkbox"
                                   data-actuator="{{ $a['key'] }}" {{ $a['is_on'] ? 'checked' : '' }}>
                        </div>
                    </div>
                    <div class="fw-bold mt-3" style="font-size:1.02rem;">{{ $a['label'] }}</div>
                    <div class="mt-1 actuator-state">
                        @if ($a['is_on'])
                            <span class="badge-status badge-normal"><span class="dot"></span>{{ $a['on_state'] }}</span>
                        @else
                            <span class="badge-status badge-neutral"><span class="dot"></span>{{ $a['off_state'] }}</span>
                        @endif
                    </div>

                    @if ($a['is_on'] && $latest)
                        <div class="field-block mt-3 d-flex justify-content-between align-items-center">
                            <span class="field-label">Running for</span>
                            <span class="field-value mono running-timer" data-since="{{ ($latest->executed_at ?? $latest->sent_at ?? $latest->created_at)->toIso8601String() }}">00:00:00</span>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-end mt-3 pt-3 border-top">
                        <div>
                            <div class="field-label">Last Run</div>
                            <div style="font-size:.85rem;">
                                {{ $latest ? ($latest->created_at->isToday() ? 'Today '.$latest->created_at->format('H:i') : $latest->created_at->format('M d H:i')) : 'Never' }}
                            </div>
                        </div>
                        @if ($latest)
                            <span class="pill-source pill-{{ $latest->source }}">{{ ucfirst($latest->source) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Automation status --}}
    <div class="mb-2"><div class="section-title">Automation Status</div></div>
    <div class="gh-card p-0" style="overflow:hidden;">
        <div class="banner banner-success m-0" style="border-radius:0; border-left:none; border-right:none; border-top:none;">
            <span class="stat-icon tone-green">@include('partials.icon', ['name' => 'gauge', 'size' => 20])</span>
            <div class="flex-fill">
                <div class="fw-bold">Rule Engine: Active</div>
                <div style="font-size:.85rem;">{{ collect($rules)->where('enabled', true)->count() }} of {{ count($rules) }} rules enabled · evaluated on each reading</div>
            </div>
            <span class="badge-status badge-normal"><span class="dot"></span>Healthy</span>
        </div>
        <div class="table-responsive">
            <table class="gh-table">
                <thead><tr><th>Rule Name</th><th>Condition</th><th>Action</th><th>Last Triggered</th><th class="text-end">Status</th></tr></thead>
                <tbody>
                    @foreach ($rules as $rule)
                        <tr>
                            <td class="fw-semibold">{{ $rule['name'] }}</td>
                            <td><span class="field-block d-inline-block mono py-1 px-2" style="font-size:.8rem;">{{ $rule['condition'] }}</span></td>
                            <td style="color: var(--accent); font-weight:600;">→ {{ $rule['action'] }}</td>
                            <td class="text-muted-2">{{ $rule['last_triggered']?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-2">
                                    <span class="{{ $rule['enabled'] ? '' : 'text-muted-2' }} fw-semibold" style="font-size:.85rem; {{ $rule['enabled'] ? 'color:var(--status-normal);' : '' }}">
                                        {{ $rule['enabled'] ? 'Enabled' : 'Disabled' }}
                                    </span>
                                    @if (!empty($rule['key']))
                                        <form method="POST" action="{{ route('control.rule-toggle', $rule['key']) }}" class="m-0">
                                            @csrf
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" {{ $rule['enabled'] ? 'checked' : '' }} onchange="this.form.submit()">
                                            </div>
                                        </form>
                                    @else
                                        <a href="{{ route('schedules.index') }}" class="text-muted-2" style="font-size:.72rem; white-space:nowrap;" title="Managed on the Schedules page">in Schedules ›</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const toggleUrl = "{{ route('control.toggle') }}";
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const ghId = @json(optional($currentGreenhouse)->id);

    document.querySelectorAll('.actuator-toggle').forEach(sw => {
        sw.addEventListener('change', async function () {
            const actuator = this.dataset.actuator;
            const command = this.checked ? 'on' : 'off';
            this.disabled = true;
            try {
                const res = await fetch(toggleUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ actuator, command, greenhouse: ghId }),
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                if (data.delivered === false) {
                    alert(data.message || 'Device offline — command queued.');
                }
                setTimeout(() => window.location.reload(), 400);
            } catch (e) {
                alert('Could not send command.');
                this.checked = !this.checked;
                this.disabled = false;
            }
        });
    });

    // Live "running for" timers.
    function tickTimers() {
        document.querySelectorAll('.running-timer').forEach(el => {
            const since = new Date(el.dataset.since).getTime();
            let s = Math.max(0, Math.floor((Date.now() - since) / 1000));
            const h = String(Math.floor(s / 3600)).padStart(2, '0');
            const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
            const sec = String(s % 60).padStart(2, '0');
            el.textContent = `${h}:${m}:${sec}`;
        });
    }
    tickTimers();
    setInterval(tickTimers, 1000);
</script>
@endpush
