@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Live overview · ' . ($latestReading ? 'last reading ' . $latestReading->recorded_at->diffForHumans() : 'no readings yet'))

@section('content')
    {{-- ROW 1 — Stat cards --}}
    <div class="row g-3 mb-3">
        @foreach ($metrics as $m)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="gh-card stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-icon {{ $m['tone'] }}">@include('partials.icon', ['name' => $m['icon'], 'size' => 22])</span>
                        @if ($m['trend_delta'] !== null && $m['trend_delta'] != 0)
                            @php $up = $m['trend_delta'] > 0; @endphp
                            <span class="{{ $up ? 'trend-up' : 'trend-down' }}">
                                {{ $up ? '▲' : '▼' }} {{ $up ? '+' : '' }}{{ rtrim(rtrim(number_format($m['trend_delta'], 1), '0'), '.') }}{{ $m['unit'] === '°C' ? '°' : $m['unit'] }}
                            </span>
                        @endif
                    </div>
                    <div class="stat-name mt-3">{{ $m['label'] }}</div>
                    <div class="stat-value my-1">
                        {{ $m['value'] !== null ? rtrim(rtrim(number_format($m['value'], 1), '0'), '.') : '—' }}<span class="unit"> {{ $m['unit'] }}</span>
                    </div>
                    <div class="mt-2">@include('partials.badge', ['status' => $m['status']])</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ROW 2 — Charts --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <div class="gh-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <div class="section-title">Climate · 24 hours</div>
                        <div class="section-sub">Temperature &amp; humidity trend</div>
                    </div>
                </div>
                <div style="position:relative; height:260px;"><canvas id="climateChart"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="gh-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <div class="section-title">Reserves · 24 hours</div>
                        <div class="section-sub">Soil moisture &amp; water level</div>
                    </div>
                </div>
                <div style="position:relative; height:260px;"><canvas id="reservesChart"></canvas></div>
            </div>
        </div>
    </div>

    {{-- ROW 3 — Recent alerts --}}
    <div class="gh-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="section-title">Recent Alerts</span>
                @if ($criticalCount > 0)
                    <span class="badge-status badge-critical"><span class="dot"></span>{{ $criticalCount }} critical</span>
                @endif
            </div>
            <a href="{{ route('alerts.index') }}" class="text-decoration-none fw-semibold" style="color: var(--accent)">View all alerts</a>
        </div>
        <div class="table-responsive">
            <table class="gh-table">
                <thead>
                    <tr><th>Severity</th><th>Location</th><th>Source</th><th>Message</th><th class="text-end">Time</th></tr>
                </thead>
                <tbody>
                    @forelse ($recentAlerts as $alert)
                        <tr class="{{ $alert->severity === 'critical' ? 'row-critical' : ($alert->severity === 'warning' ? 'row-warning' : '') }}">
                            <td>@include('partials.badge', ['status' => $alert->severity])</td>
                            <td class="fw-semibold">{{ $alert->greenhouse->name ?? '—' }}</td>
                            <td class="text-muted-2">{{ $alert->device->name ?? '—' }}</td>
                            <td>{{ $alert->message }}</td>
                            <td class="text-end text-muted-2">{{ $alert->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">No alerts for this greenhouse.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // app.js is loaded as a deferred module, so wait for it (and window.Chart) before drawing.
    document.addEventListener('DOMContentLoaded', function () {
    const chartData = @json($chartData);

    function lineCfg(datasets, opts = {}) {
        return {
            type: 'line',
            data: { labels: chartData.labels, datasets },
            options: Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } } },
                scales: { x: { grid: { display: false } } },
            }, opts),
        };
    }

    new Chart(document.getElementById('climateChart'), lineCfg([
        { label: 'Temp', data: chartData.temperature, borderColor: '#2d7a4f', backgroundColor: 'rgba(45,122,79,0.08)', tension: 0.4, fill: true, yAxisID: 'y', pointRadius: 0 },
        { label: 'Humidity', data: chartData.humidity, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.05)', tension: 0.4, fill: false, yAxisID: 'y1', pointRadius: 0 },
    ], {
        scales: {
            x: { grid: { display: false } },
            y: { position: 'left', title: { display: false } },
            y1: { position: 'right', grid: { drawOnChartArea: false } },
        },
    }));

    new Chart(document.getElementById('reservesChart'), lineCfg([
        { label: 'Soil', data: chartData.soil_moisture, borderColor: '#a16207', backgroundColor: 'rgba(161,98,7,0.12)', tension: 0.4, fill: true, pointRadius: 0 },
        { label: 'Water', data: chartData.water_level_cm, borderColor: '#ea580c', backgroundColor: 'rgba(234,88,12,0.10)', tension: 0.4, fill: true, pointRadius: 0 },
    ]));
    });
</script>
@endpush
