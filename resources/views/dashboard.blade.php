@extends('layouts.app')

@section('page-title')
    <h2 style="font-size:20px;font-weight:600;">Dashboard</h2>
@endsection

@section('content')
    @php
        $rangeLabel = $analytics['is_today'] ? 'Today' : 'In range';
        // Sparkline data per type, keyed the same way as the analytics counts.
        $sparks = [];
        foreach (\App\Support\RecordType::slugs() as $type) {
            $sparks[$type] = $analytics['series'][$type];
        }
        $sparks['total'] = $analytics['series']['total'];
        $peak = function (array $values) {
            return max(1, max($values ?: [1]));
        };
        $pct = fn ($v, $max) => $max > 0 ? max($v > 0 ? 8 : 3, (int) round($v / $max * 100)) : 3;
        $axis = ['first' => $analytics['series']['labels'][0] ?? '', 'last' => $analytics['series']['labels'][count($analytics['series']['labels']) - 1] ?? ''];
        // Glyph per type, kept here so App\Support\RecordType stays data-only.
        $typeIcons = [
            'error' => '<path d="M12 8v5"/><path d="M12 16.5h.01"/><circle cx="12" cy="12" r="9"/>',
            'success' => '<path d="M20 6 9 17l-5-5"/>',
            'mission' => '<path d="M12 21s-7-4.5-7-10a7 7 0 1114 0c0 5.5-7 10-7 10z"/><circle cx="12" cy="11" r="2.5"/>',
        ];
    @endphp

    <div class="card">
        <div class="page-head" style="margin-bottom:.9rem;align-items:flex-end;">
            <div>
                <h3 style="margin:0;font-size:16px;">Record Analytics</h3>
                <div class="hint">{{ $analytics['label'] }} · compared with {{ $analytics['prev_label'] }}</div>
            </div>
            <div class="actions" style="align-items:flex-end;">
                <form method="GET" action="{{ route('dashboard') }}" class="filter-row" style="margin-bottom:0;align-items:flex-end;">
                    <div class="field">
                        <label for="from">From</label>
                        <input type="date" name="from" id="from" value="{{ $from->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}">
                    </div>
                    <div class="field">
                        <label for="to">To</label>
                        <input type="date" name="to" id="to" value="{{ $to->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}">
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn-primary">Apply</button>
                        @if (! $analytics['is_today'])
                            <a href="{{ route('dashboard') }}" class="btn-secondary">Today</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="filter-row" style="margin-bottom:1.25rem;">
            <span class="hint">Presets:</span>
            <a href="{{ route('dashboard') }}" class="badge">Today</a>
            <a href="{{ route('dashboard', ['from' => $today->copy()->subDays(6)->format('Y-m-d'), 'to' => $today->format('Y-m-d')]) }}" class="badge">Last 7 Days</a>
            <a href="{{ route('dashboard', ['from' => $today->copy()->subDays(29)->format('Y-m-d'), 'to' => $today->format('Y-m-d')]) }}" class="badge">Last 30 Days</a>
            <a href="{{ route('dashboard', ['from' => $today->copy()->startOfMonth()->format('Y-m-d'), 'to' => $today->format('Y-m-d')]) }}" class="badge">This Month</a>
        </div>

        <div class="kpis">
            {{-- One card per known record type, so a new type appears here without
                 touching this view. --}}
            @foreach (\App\Support\RecordType::slugs() as $type)
                @php
                    $d = $analytics['delta'][$type];
                    $vals = $sparks[$type];
                    $max = $peak($vals);
                @endphp
                <div class="kpi" data-tone="{{ $type }}">
                    <div class="kpi-head">
                        <span class="kpi-label">{{ \App\Support\RecordType::label($type) }}</span>
                        <span class="kpi-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">{!! $typeIcons[$type] ?? '' !!}</svg>
                        </span>
                    </div>
                    <div class="kpi-value">{{ $analytics['counts'][$type] }}<small> {{ $rangeLabel }}</small></div>
                    <div class="kpi-foot">
                        <span class="delta delta-{{ $d['dir'] }}">@if ($d['dir'] === 'up')&uarr;@elseif ($d['dir'] === 'down')&darr;@endif{{ $d['text'] }}</span>
                        <span>vs previous period</span>
                    </div>
                    <div class="spark" aria-hidden="true">
                        @foreach ($vals as $v)
                            <span class="{{ $v > 0 ? 'on' : '' }}" style="height:{{ $pct($v, $max) }}%"></span>
                        @endforeach
                    </div>
                    <div class="spark-axis"><span>{{ $axis['first'] }}</span><span>{{ $analytics['series']['axis'] }}</span><span>{{ $axis['last'] }}</span></div>
                </div>
            @endforeach

            <div class="kpi" data-tone="total">
                <div class="kpi-head">
                    <span class="kpi-label">Total Records</span>
                    <span class="kpi-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/></svg>
                    </span>
                </div>
                <div class="kpi-value">{{ $analytics['total'] }}<small> {{ $rangeLabel }}</small></div>
                <div class="kpi-foot">
                    @php $d = $analytics['delta']['total']; @endphp
                    <span class="delta delta-{{ $d['dir'] }}">@if ($d['dir'] === 'up')&uarr;@elseif ($d['dir'] === 'down')&darr;@endif{{ $d['text'] }}</span>
                    <span>vs previous period</span>
                </div>
                @php $vals = $sparks['total']; $max = $peak($vals); @endphp
                <div class="spark" aria-hidden="true">
                    @foreach ($vals as $v)
                        <span class="{{ $v > 0 ? 'on' : '' }}" style="height:{{ $pct($v, $max) }}%"></span>
                    @endforeach
                </div>
                <div class="spark-axis"><span>{{ $axis['first'] }}</span><span>{{ $analytics['series']['axis'] }}</span><span>{{ $axis['last'] }}</span></div>
            </div>

            <div class="kpi" data-tone="mix">
                <div class="kpi-head">
                    <span class="kpi-label">Error Rate</span>
                    <span class="kpi-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/></svg>
                    </span>
                </div>
                <div class="kpi-value">{{ $analytics['error_rate'] }}<small>%</small></div>
                <div class="kpi-foot">
                    <span>{{ $analytics['error'] }} error · {{ $analytics['success'] }} success · {{ $analytics['mission'] }} mission</span>
                </div>
                <div class="mix" role="img" aria-label="Error {{ $analytics['error'] }}, Success {{ $analytics['success'] }}, Medical Mission {{ $analytics['mission'] }}">
                    @foreach (\App\Support\RecordType::slugs() as $type)
                        <span class="mix-{{ $type }}" style="width:{{ $analytics['total'] > 0 ? round($analytics['counts'][$type] / $analytics['total'] * 100) : 0 }}%"></span>
                    @endforeach
                </div>
                <div class="spark-axis"><span>Error</span><span>Split</span><span>Mission</span></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="page-head" style="margin-bottom:.5rem;">
            <h3>Recent Records</h3>
            <a href="{{ route('records.error') }}">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Patient</th><th>PhilHealth ID</th><th>Generated</th><th style="text-align:right;">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($recentRecords as $record)
                        <tr>
                            <td><strong>{{ $record->patient_name }}</strong><br><span style="color:var(--muted);font-size:13px;">{{ $record->birthdate?->format('M j, Y') ?? '—' }}</span></td>
                            <td><span class="mono">{{ $record->philhealth_id }}</span></td>
                            <td>{{ $record->documentGenerations->sortByDesc('generated_at')->first()?->generated_at->format('M j, Y g:i A') ?? '—' }}</td>
                            <td style="text-align:right;">
                                <span class="row-actions">
                                    <a href="{{ route('records.show', $record) }}">View</a>
                                    @if ($record->status === 'generated' || $record->status === 'printed')
                                        <a href="{{ route('records.print', $record) }}?print=1" target="_blank">Print</a>
                                        <a href="{{ route('records.download', $record) }}">Download</a>
                                    @elseif ($record->status === 'draft')
                                        <a href="{{ route('records.generate', $record) }}">Generate</a>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $recentRecords->links() }}
    </div>
@endsection