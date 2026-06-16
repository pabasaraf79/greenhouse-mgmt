@extends('layouts.app')

@section('title', 'Alerts')
@section('subtitle', $counts['active'].' active event' . ($counts['active'] === 1 ? '' : 's'))

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="filter-tabs">
            @php
                $tabs = [
                    'all' => ['All', $counts['all']],
                    'active' => ['Active', $counts['active']],
                    'warning' => ['Warning', $counts['warning']],
                    'critical' => ['Critical', $counts['critical']],
                    'resolved' => ['Resolved', $counts['resolved']],
                ];
            @endphp
            @foreach ($tabs as $key => [$label, $count])
                <a class="filter-tab {{ $filter === $key ? 'active' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['filter' => $key, 'page' => null]) }}">
                    {{ $label }} <span class="count">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" class="d-flex align-items-center gap-2">
            <input type="hidden" name="filter" value="{{ $filter }}">
            @if (request('greenhouse'))<input type="hidden" name="greenhouse" value="{{ request('greenhouse') }}">@endif
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:auto;">
            <span class="text-muted-2">–</span>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:auto;">
            <button class="btn btn-soft btn-sm" type="submit">Filter</button>
        </form>
    </div>

    <div class="gh-card p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="gh-table">
                <thead>
                    <tr>
                        <th>Severity</th><th>Greenhouse</th><th>Device / Source</th><th>Parameter</th>
                        <th>Value</th><th>Threshold</th><th>Message</th><th>Time</th><th>Status</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alerts as $alert)
                        <tr class="{{ $alert->status === 'resolved' ? 'row-resolved' : ($alert->severity === 'critical' ? 'row-critical' : ($alert->severity === 'warning' ? 'row-warning' : '')) }}">
                            <td>@include('partials.badge', ['status' => $alert->severity])</td>
                            <td class="fw-semibold">{{ $alert->greenhouse->name ?? '—' }}</td>
                            <td class="text-muted-2">{{ $alert->device->name ?? '—' }}</td>
                            <td class="text-capitalize">{{ str_replace('_', ' ', $alert->parameter) }}</td>
                            <td class="fw-semibold mono">{{ $alert->value }}</td>
                            <td class="text-muted-2 mono">{{ $thresholdDisplays[$alert->id] ?? '—' }}</td>
                            <td>{{ $alert->message }}</td>
                            <td class="text-muted-2">{{ $alert->created_at->diffForHumans() }}</td>
                            <td>@include('partials.badge', ['status' => $alert->status])</td>
                            <td class="text-end">
                                @if ($alert->status === 'active')
                                    <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-soft btn-sm" type="submit">Acknowledge</button>
                                    </form>
                                @elseif ($alert->status === 'acknowledged')
                                    <form method="POST" action="{{ route('alerts.resolve', $alert) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-accent btn-sm" type="submit">Resolve</button>
                                    </form>
                                @else
                                    <span class="text-muted-2">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-muted-2 text-center py-4">No alerts match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted-2" style="font-size:.82rem;">
            @include('partials.icon', ['name' => 'alert', 'size' => 13])
            Showing {{ $alerts->count() }} of {{ $alerts->total() }} events · resolved alerts retained 30 days
        </div>
        {{ $alerts->links() }}
    </div>
@endsection
