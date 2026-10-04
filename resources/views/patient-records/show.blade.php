@extends('layouts.app')

@section('page-title')
    <h2 style="font-size:20px;font-weight:600;">Patient Record</h2>
@endsection

@section('content')
    @php
        $isSuccess = ($record->record_type ?? 'error') === 'success';
        $initials = collect(preg_split('/\s+/', trim($record->patient_name)))
            ->filter()
            ->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
        $statusPill = ['draft' => 'pill-amber', 'generated' => 'pill-blue', 'printed' => 'pill-green'][$record->status] ?? 'pill-slate';
    @endphp

    <div class="card">
        <div class="record-hero">
            <div class="avatar-lg" aria-hidden="true">{{ $initials }}</div>

            <div class="who">
                <h3>{{ $record->patient_name }}</h3>
                <div class="sub">
                    <span class="mono">{{ $record->philhealth_id }}</span>
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ $record->birthdate?->format('F j, Y') ?? 'Birthdate not recorded' }}</span>
                </div>
                <div class="sub" style="margin-top:.5rem;">
                    <span class="pill {{ $isSuccess ? 'pill-green' : 'pill-red' }}">
                        <span class="pill-dot"></span>PCU {{ ucfirst($record->record_type ?? 'error') }}
                    </span>
                    <span class="pill {{ $statusPill }}">
                        <span class="pill-dot"></span>{{ ucfirst($record->status) }}
                    </span>
                    <span>{{ $record->template->name }} <span class="mono">v{{ $record->template->version }}</span></span>
                </div>
            </div>

            <div class="hero-actions">
                @can('generate', $record)
                    @if ($record->status === 'draft')
                        <a href="{{ route('records.generate', $record) }}" class="btn-primary">Generate &amp; Print</a>
                    @else
                        <a href="{{ route('records.print', $record) }}?print=1" target="_blank" class="btn-primary">Print</a>
                        <a href="{{ route('records.download', $record) }}" class="btn-secondary">Download Excel</a>
                    @endif
                @endcan
                @can('update', $record)
                    <a href="{{ route('records.edit', $record) }}" class="btn-secondary">Edit</a>
                @endcan
                <a href="{{ $isSuccess ? route('records.success') : route('records.error') }}" class="btn-secondary">&larr; Back</a>
            </div>
        </div>

        @can('generate', $record)
            @if ($record->status === 'generated')
                <form action="{{ route('records.mark-printed', $record) }}" method="POST" style="margin-top:1.1rem;padding-top:1.1rem;border-top:1px solid var(--line-2);">
                    @csrf
                    <div class="actions" style="align-items:center;">
                        <span class="hint" style="margin:0;">Generated but not yet marked as printed.</span>
                        <button type="submit" class="btn-secondary">Mark as Printed</button>
                    </div>
                </form>
            @endif
        @endcan
    </div>

    <div class="card">
        <h3>Record Information</h3>
        <div class="kv wide">
            <div>
                <div class="k">PhilHealth ID</div>
                <div class="v mono">{{ $record->philhealth_id }}</div>
            </div>
            <div>
                <div class="k">Birthdate</div>
                <div class="v">{{ $record->birthdate?->format('F j, Y') ?? '—' }}</div>
            </div>
            @if ($record->appointment_date)
                <div>
                    <div class="k">Date of Appointment</div>
                    <div class="v">{{ $record->appointment_date->format('F j, Y') }}</div>
                </div>
            @endif
            @if ($record->auth_transaction_code)
                <div>
                    <div class="k">Authorization Transaction Code</div>
                    <div class="v mono">{{ $record->auth_transaction_code }}</div>
                </div>
            @endif
            @if ($record->pcu_error_code)
                <div>
                    <div class="k">PCU Error Code</div>
                    <div class="v mono">{{ $record->pcu_error_code }}</div>
                </div>
            @endif
            <div>
                <div class="k">Head of Clinic</div>
                <div class="v">{{ $record->head_of_clinic }}</div>
            </div>
            <div>
                <div class="k">Document Date</div>
                <div class="v">{{ $record->date_today?->format('F j, Y') ?? 'Not yet generated' }}</div>
            </div>
            <div>
                <div class="k">Created By</div>
                <div class="v">{{ $record->creator->name ?? '—' }}</div>
            </div>
            <div>
                <div class="k">Created</div>
                <div class="v">{{ $record->created_at->format('M j, Y g:i A') }}</div>
            </div>
            <div>
                <div class="k">Last Updated</div>
                <div class="v">{{ $record->updated_at->format('M j, Y g:i A') }}</div>
            </div>
        </div>
    </div>

    @if ($record->image_with_id_url || $record->empanelment_error_image_url)
        <div class="card">
            <h3>Evidence</h3>
            <div class="evidence-row">
                <div>
                    <div class="k" style="margin-bottom:.35rem;">ID Image</div>
                    @if ($record->image_with_id_url)
                        <a href="{{ $record->image_with_id_url }}" target="_blank" rel="noopener" class="shot">
                            <img src="{{ $record->image_with_id_url }}" alt="ID Image of {{ $record->patient_name }}">
                            <span class="zoom">View full size</span>
                        </a>
                    @else
                        <div class="shot-empty">No ID image uploaded</div>
                    @endif
                </div>

                @if ($record->empanelment_error_image_url)
                    <div>
                        <div class="k" style="margin-bottom:.35rem;">Empanelment Error Image</div>
                        <a href="{{ $record->empanelment_error_image_url }}" target="_blank" rel="noopener" class="shot">
                            <img src="{{ $record->empanelment_error_image_url }}" alt="Empanelment error image of {{ $record->patient_name }}">
                            <span class="zoom">View full size</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($generations->total() > 0)
        <div class="card">
            <div class="page-head" style="margin-bottom:.5rem;">
                <h3>Generation History</h3>
                <span class="hint">{{ $generations->total() }} total</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Generated</th><th>Template Version</th><th>Generated By</th><th style="text-align:right;">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($generations as $generation)
                            <tr>
                                <td>{{ $generation->generated_at->format('M j, Y g:i A') }}</td>
                                <td><span class="mono">{{ $generation->template_version }}</span></td>
                                <td>{{ $generation->generator->name }}</td>
                                <td style="text-align:right;">
                                    <span class="row-actions">
                                        <a href="{{ route('generations.download', $generation) }}">Download</a>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $generations->links() }}
        </div>
    @endif
@endsection