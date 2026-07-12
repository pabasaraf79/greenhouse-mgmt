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

    {{-- ROW 4 — Crop Activity Record --}}
    <div class="gh-card mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="section-title">Crop Activity Record</div>
                <div class="section-sub">Track farm operations, planting, and harvesting activity</div>
            </div>
            @if (auth()->user()->isAdmin())
                <button class="btn btn-accent btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addCropActivityForm" aria-expanded="false" aria-controls="addCropActivityForm">
                    + Add Activity
                </button>
            @endif
        </div>

        @if (auth()->user()->isAdmin())
            <div class="collapse mb-4" id="addCropActivityForm">
                <div class="card card-body bg-light border-0 p-3">
                    <form action="{{ route('crop-activities.store') }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                                <label for="activity" class="form-label fw-semibold" style="font-size: 0.85rem;">Activity</label>
                                <select name="activity" id="activity" class="form-select form-select-sm" required>
                                    <option value="" disabled selected>Select activity...</option>
                                    <option value="Seeding">Seeding</option>
                                    <option value="Transplanting">Transplanting</option>
                                    <option value="Fertilization">Fertilization</option>
                                    <option value="Pest Control">Pest Control</option>
                                    <option value="Watering">Watering</option>
                                    <option value="Harvesting">Harvesting</option>
                                    <option value="Pruning">Pruning</option>
                                    <option value="Soil Tillage">Soil Tillage</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-2">
                                <label for="date" class="form-label fw-semibold" style="font-size: 0.85rem;">Date</label>
                                <input type="date" name="date" id="date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="field_block" class="form-label fw-semibold" style="font-size: 0.85rem;">Field Block</label>
                                <input type="text" name="field_block" id="field_block" class="form-control form-control-sm" placeholder="e.g. Block A, Greenhouse 1" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="variety" class="form-label fw-semibold" style="font-size: 0.85rem;">Variety</label>
                                <input type="text" name="variety" id="variety" class="form-control form-control-sm" placeholder="e.g. Roma Tomato, Butterhead Lettuce" required>
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label fw-semibold" style="font-size: 0.85rem;">Notes</label>
                                <textarea name="notes" id="notes" class="form-control form-control-sm" rows="2" placeholder="Any observation, crop health notes or details..."></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-accent btn-sm">Save Activity</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="gh-table">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>Date</th>
                        <th>Field Block</th>
                        <th>Variety</th>
                        <th>Notes</th>
                        @if (auth()->user()->isAdmin())
                            <th class="text-end">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cropActivities as $record)
                        <tr>
                            <td class="fw-semibold"><span class="badge-status badge-neutral"><span class="dot"></span>{{ $record->activity }}</span></td>
                            <td>{{ $record->date->format('Y-m-d') }}</td>
                            <td class="text-muted-2">{{ $record->field_block }}</td>
                            <td>{{ $record->variety }}</td>
                            <td class="text-muted-2" style="font-size: 0.85rem;">{{ $record->notes ?? '—' }}</td>
                            @if (auth()->user()->isAdmin())
                                <td class="text-end">
                                    <button class="btn btn-soft btn-sm py-1 px-2" type="button" data-bs-toggle="modal" data-bs-target="#editCropActivityModal{{ $record->id }}">
                                        Edit
                                    </button>
                                </td>
                            @endif
                        </tr>

                        @if (auth()->user()->isAdmin())
                            <!-- Edit Modal for {{ $record->id }} -->
                            <div class="modal fade" id="editCropActivityModal{{ $record->id }}" tabindex="-1" aria-labelledby="editCropActivityModalLabel{{ $record->id }}" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('crop-activities.update', $record) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <div class="modal-header border-bottom-0 pb-0">
                                                <h5 class="modal-title fw-bold" id="editCropActivityModalLabel{{ $record->id }}">Edit Crop Activity</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="activity{{ $record->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Activity</label>
                                                    <select name="activity" id="activity{{ $record->id }}" class="form-select form-select-sm" required>
                                                        <option value="Seeding" {{ $record->activity === 'Seeding' ? 'selected' : '' }}>Seeding</option>
                                                        <option value="Transplanting" {{ $record->activity === 'Transplanting' ? 'selected' : '' }}>Transplanting</option>
                                                        <option value="Fertilization" {{ $record->activity === 'Fertilization' ? 'selected' : '' }}>Fertilization</option>
                                                        <option value="Pest Control" {{ $record->activity === 'Pest Control' ? 'selected' : '' }}>Pest Control</option>
                                                        <option value="Watering" {{ $record->activity === 'Watering' ? 'selected' : '' }}>Watering</option>
                                                        <option value="Harvesting" {{ $record->activity === 'Harvesting' ? 'selected' : '' }}>Harvesting</option>
                                                        <option value="Pruning" {{ $record->activity === 'Pruning' ? 'selected' : '' }}>Pruning</option>
                                                        <option value="Soil Tillage" {{ $record->activity === 'Soil Tillage' ? 'selected' : '' }}>Soil Tillage</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="date{{ $record->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Date</label>
                                                    <input type="date" name="date" id="date{{ $record->id }}" class="form-control form-control-sm" value="{{ $record->date->format('Y-m-d') }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="field_block{{ $record->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Field Block</label>
                                                    <input type="text" name="field_block" id="field_block{{ $record->id }}" class="form-control form-control-sm" value="{{ $record->field_block }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="variety{{ $record->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Variety</label>
                                                    <input type="text" name="variety" id="variety{{ $record->id }}" class="form-control form-control-sm" value="{{ $record->variety }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="notes{{ $record->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Notes</label>
                                                    <textarea name="notes" id="notes{{ $record->id }}" class="form-control form-control-sm" rows="3">{{ $record->notes }}</textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top-0 pt-0">
                                                <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-accent btn-sm">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}" class="text-center text-muted-2 py-4">No crop activity records entered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ROW 5 — Agricultural Input & Purchase Register --}}
    <div class="gh-card mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="section-title">Agricultural Input & Purchase Register</div>
                <div class="section-sub">Record purchased fertilizers, seeds, and inputs and track their usage</div>
            </div>
            @if (auth()->user()->isAdmin())
                <button class="btn btn-accent btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addInputRegisterForm" aria-expanded="false" aria-controls="addInputRegisterForm">
                    + Add Purchase
                </button>
            @endif
        </div>

        @if (auth()->user()->isAdmin())
            <div class="collapse mb-4" id="addInputRegisterForm">
                <div class="card card-body bg-light border-0 p-3">
                    <form action="{{ route('agricultural-inputs.store') }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                                <label for="purchase_date" class="form-label fw-semibold" style="font-size: 0.85rem;">Purchase Date</label>
                                <input type="date" name="purchase_date" id="purchase_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="input" class="form-label fw-semibold" style="font-size: 0.85rem;">Input Name / Type</label>
                                <input type="text" name="input" id="input" class="form-control form-control-sm" placeholder="e.g. Urea, NPK 15-15-15, Tomato Seeds" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="supplier" class="form-label fw-semibold" style="font-size: 0.85rem;">Supplier</label>
                                <input type="text" name="supplier" id="supplier" class="form-control form-control-sm" placeholder="e.g. Green Agro Ltd" required>
                            </div>
                            <div class="col-6 col-md-1.5">
                                <label for="qty" class="form-label fw-semibold" style="font-size: 0.85rem;">Qty</label>
                                <input type="number" step="0.01" name="qty" id="qty" class="form-control form-control-sm" placeholder="Quantity" required>
                            </div>
                            <div class="col-6 col-md-1.5">
                                <label for="unit_price" class="form-label fw-semibold" style="font-size: 0.85rem;">Unit Price (LKR)</label>
                                <input type="number" step="0.01" name="unit_price" id="unit_price" class="form-control form-control-sm" placeholder="Price each" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="expiry" class="form-label fw-semibold" style="font-size: 0.85rem;">Expiry Date</label>
                                <input type="date" name="expiry" id="expiry" class="form-control form-control-sm">
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="used_on" class="form-label fw-semibold" style="font-size: 0.85rem;">Used On (Date)</label>
                                <input type="date" name="used_on" id="used_on" class="form-control form-control-sm">
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-accent btn-sm">Save Purchase</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="gh-table">
                <thead>
                    <tr>
                        <th>Purchase Date</th>
                        <th>Input</th>
                        <th>Supplier</th>
                        <th>Qty</th>
                        <th>Unit Price (LKR)</th>
                        <th>Total (LKR)</th>
                        <th>Expiry</th>
                        <th>Used On</th>
                        @if (auth()->user()->isAdmin())
                            <th class="text-end">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agriculturalInputs as $input)
                        <tr>
                            <td>{{ $input->purchase_date->format('Y-m-d') }}</td>
                            <td class="fw-semibold">{{ $input->input }}</td>
                            <td class="text-muted-2">{{ $input->supplier }}</td>
                            <td>{{ rtrim(rtrim(number_format($input->qty, 2), '0'), '.') }}</td>
                            <td>{{ number_format($input->unit_price, 2) }}</td>
                            <td class="fw-bold">{{ number_format($input->total, 2) }}</td>
                            <td class="text-muted-2">{{ $input->expiry ? $input->expiry->format('Y-m-d') : '—' }}</td>
                            <td>
                                @if ($input->used_on)
                                    <span class="badge-status badge-normal"><span class="dot"></span>{{ $input->used_on->format('Y-m-d') }}</span>
                                @else
                                    <span class="text-muted-2">Unused</span>
                                @endif
                            </td>
                            @if (auth()->user()->isAdmin())
                                <td class="text-end">
                                    <button class="btn btn-soft btn-sm py-1 px-2" type="button" data-bs-toggle="modal" data-bs-target="#editAgriculturalInputModal{{ $input->id }}">
                                        Edit
                                    </button>
                                </td>
                            @endif
                        </tr>

                        @if (auth()->user()->isAdmin())
                            <!-- Edit Modal for {{ $input->id }} -->
                            <div class="modal fade" id="editAgriculturalInputModal{{ $input->id }}" tabindex="-1" aria-labelledby="editAgriculturalInputModalLabel{{ $input->id }}" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('agricultural-inputs.update', $input) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <div class="modal-header border-bottom-0 pb-0">
                                                <h5 class="modal-title fw-bold" id="editAgriculturalInputModalLabel{{ $input->id }}">Edit Input / Purchase</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="purchase_date{{ $input->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Purchase Date</label>
                                                    <input type="date" name="purchase_date" id="purchase_date{{ $input->id }}" class="form-control form-control-sm" value="{{ $input->purchase_date->format('Y-m-d') }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="input{{ $input->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Input Name / Type</label>
                                                    <input type="text" name="input" id="input{{ $input->id }}" class="form-control form-control-sm" value="{{ $input->input }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="supplier{{ $input->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Supplier</label>
                                                    <input type="text" name="supplier" id="supplier{{ $input->id }}" class="form-control form-control-sm" value="{{ $input->supplier }}" required>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label for="qty{{ $input->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Qty</label>
                                                        <input type="number" step="0.01" name="qty" id="qty{{ $input->id }}" class="form-control form-control-sm" value="{{ $input->qty }}" required>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label for="unit_price{{ $input->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Unit Price (LKR)</label>
                                                        <input type="number" step="0.01" name="unit_price" id="unit_price{{ $input->id }}" class="form-control form-control-sm" value="{{ $input->unit_price }}" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="expiry{{ $input->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Expiry Date</label>
                                                    <input type="date" name="expiry" id="expiry{{ $input->id }}" class="form-control form-control-sm" value="{{ $input->expiry ? $input->expiry->format('Y-m-d') : '' }}">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="used_on{{ $input->id }}" class="form-label fw-semibold" style="font-size: 0.85rem;">Used On (Date)</label>
                                                    <input type="date" name="used_on" id="used_on{{ $input->id }}" class="form-control form-control-sm" value="{{ $input->used_on ? $input->used_on->format('Y-m-d') : '' }}">
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top-0 pt-0">
                                                <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-accent btn-sm">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isAdmin() ? 9 : 8 }}" class="text-center text-muted-2 py-4">No agricultural input records entered yet.</td>
                        </tr>
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
