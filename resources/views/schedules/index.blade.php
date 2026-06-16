@extends('layouts.app')

@section('title', 'Schedules')
@section('subtitle', $currentGreenhouse ? 'Automated fertigation & irrigation · '.$currentGreenhouse->name : 'Automated fertigation & irrigation')

@section('content')
    @php
        $days = ['mon' => 'M', 'tue' => 'T', 'wed' => 'W', 'thu' => 'T', 'fri' => 'F', 'sat' => 'S', 'sun' => 'S'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="filter-tabs">
            <a class="filter-tab {{ $tab === 'fertigation' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'fertigation']) }}">
                @include('partials.icon', ['name' => 'flask', 'size' => 15]) Fertigation Schedules <span class="count">{{ $counts['fertigation'] }}</span>
            </a>
            <a class="filter-tab {{ $tab === 'irrigation' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'irrigation']) }}">
                @include('partials.icon', ['name' => 'droplet', 'size' => 15]) Irrigation Cycles <span class="count">{{ $counts['irrigation'] }}</span>
            </a>
        </div>
        <a href="{{ route('schedules.create') }}" class="btn btn-accent">
            @include('partials.icon', ['name' => 'plus', 'size' => 16]) Add Schedule
        </a>
    </div>

    <div class="row g-3">
        @forelse ($schedules as $s)
            @php
                $active = (array) $s->days_of_week;
                $start = \Carbon\Carbon::parse($s->start_time);
            @endphp
            <div class="col-12 col-xl-6">
                <div class="gh-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="stat-icon tone-green">@include('partials.icon', ['name' => 'flask', 'size' => 20])</span>
                            <div>
                                <div class="fw-bold" style="font-size:1.02rem;">{{ $s->name }}</div>
                                <div class="text-muted-2" style="font-size:.82rem;">
                                    {{ count($active) === 7 ? 'Every day' : collect($active)->map(fn($d) => ucfirst($d))->join(' · ') }}
                                </div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('schedules.update', $s) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="name" value="{{ $s->name }}">
                            <input type="hidden" name="greenhouse_id" value="{{ $s->greenhouse_id }}">
                            <input type="hidden" name="start_time" value="{{ $s->start_time }}">
                            <input type="hidden" name="duration_minutes" value="{{ intdiv($s->duration_seconds, 60) }}">
                            <input type="hidden" name="dose_seconds" value="{{ $s->dose_seconds }}">
                            @foreach ($active as $d)<input type="hidden" name="days_of_week[]" value="{{ $d }}">@endforeach
                            <input type="hidden" name="enabled" value="{{ $s->enabled ? 0 : 1 }}">
                            <div class="form-check form-switch switch-lg m-0">
                                <input class="form-check-input" type="checkbox" {{ $s->enabled ? 'checked' : '' }}
                                       onchange="this.form.submit()">
                            </div>
                        </form>
                    </div>

                    <div class="day-pills mb-3">
                        @foreach ($days as $key => $label)
                            <span class="day-pill {{ in_array($key, $active) ? 'active' : '' }}">{{ $label }}</span>
                        @endforeach
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6"><div class="field-block"><div class="field-label">Start Time</div><div class="field-value">{{ $start->format('h:i A') }}</div></div></div>
                        <div class="col-6"><div class="field-block"><div class="field-label">Duration</div><div class="field-value">{{ intdiv($s->duration_seconds, 60) }} min</div></div></div>
                        <div class="col-6"><div class="field-block"><div class="field-label">Fert Dose</div><div class="field-value">{{ $s->dose_seconds }} sec</div></div></div>
                        <div class="col-6">
                            <div class="field-block">
                                <div class="field-label">{{ $s->enabled ? 'Next Run' : 'Status' }}</div>
                                @if ($s->enabled)
                                    <div class="field-value" style="color: var(--accent);">{{ $s->next_run_at?->isToday() ? 'Today '.$s->next_run_at->format('H:i') : ($s->next_run_at?->isTomorrow() ? 'Tomorrow '.$s->next_run_at->format('H:i') : ($s->next_run_at?->format('M d H:i') ?? '—')) }}</div>
                                @else
                                    <div class="field-value" style="color: var(--status-warning);">Disabled</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($s->enabled)
                        <div class="text-muted-2 mb-3" style="font-size:.82rem;">
                            Last run {{ $s->last_run_at ? $s->last_run_at->diffForHumans() : 'never' }}
                            @if ($s->last_run_at) <span class="badge-status badge-normal ms-1"><span class="dot"></span>Completed</span>@endif
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('schedules.edit', $s) }}" class="btn btn-soft flex-fill">@include('partials.icon', ['name' => 'edit', 'size' => 14]) Edit</a>
                            <form method="POST" action="{{ route('schedules.run-now', $s) }}" class="flex-fill">
                                @csrf
                                <button class="btn btn-accent w-100" type="submit">@include('partials.icon', ['name' => 'play', 'size' => 14]) Run Now</button>
                            </form>
                        </div>
                    @else
                        <div class="text-muted-2 mb-3" style="font-size:.82rem;">@include('partials.icon', ['name' => 'alert', 'size' => 13]) This schedule is paused — it will not run automatically.</div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('schedules.edit', $s) }}" class="btn btn-soft flex-fill">@include('partials.icon', ['name' => 'edit', 'size' => 14]) Edit</a>
                            <form method="POST" action="{{ route('schedules.update', $s) }}" class="flex-fill">
                                @csrf @method('PUT')
                                <input type="hidden" name="name" value="{{ $s->name }}">
                                <input type="hidden" name="greenhouse_id" value="{{ $s->greenhouse_id }}">
                                <input type="hidden" name="start_time" value="{{ $s->start_time }}">
                                <input type="hidden" name="duration_minutes" value="{{ intdiv($s->duration_seconds, 60) }}">
                                <input type="hidden" name="dose_seconds" value="{{ $s->dose_seconds }}">
                                @foreach ($active as $d)<input type="hidden" name="days_of_week[]" value="{{ $d }}">@endforeach
                                <input type="hidden" name="enabled" value="1">
                                <button class="btn btn-accent w-100" type="submit">@include('partials.icon', ['name' => 'power', 'size' => 14]) Enable</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-12"><div class="gh-card text-center text-muted-2">No schedules for this greenhouse. <a href="{{ route('schedules.create') }}">Add one</a>.</div></div>
        @endforelse
    </div>
@endsection
