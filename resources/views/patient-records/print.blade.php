@extends('layouts.print')

@section('content')
<div class="print-page">

    {{-- Print controls (hidden on print) --}}
    <div class="print-toolbar no-print">
        <div class="pt-meta">
            Print preview
            <strong>{{ $record->patient_name }}</strong>
        </div>
        <div class="pt-actions">
            <a href="{{ route('records.show', $record) }}" class="btn btn-secondary">Back to Record</a>
            <a href="{{ route('records.download', $record) }}" class="btn btn-secondary">Download Excel</a>
            <button type="button" onclick="window.print()" class="btn btn-primary">Print</button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-ok no-print">{{ session('success') }}</div>
    @endif

    @if (session('generation_warnings'))
        <div class="alert alert-warn no-print">
            <ul>
                @foreach ((array) session('generation_warnings') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="sheet print-document">
        @if (!empty($preview))
            {{-- Faithful render of the actual Excel template --}}
            <div class="xlsx-preview" style="aspect-ratio:{{ $preview['width'] }} / {{ $preview['height'] }};">
                @if ($preview['background'])
                    <img src="{{ $preview['background'] }}" alt="" style="position:absolute;left:{{ $preview['bgX'] / $preview['width'] * 100 }}%;top:{{ $preview['bgY'] / $preview['height'] * 100 }}%;width:{{ $preview['bgW'] / $preview['width'] * 100 }}%;height:{{ $preview['bgH'] / $preview['height'] * 100 }}%;">
                @endif
                @foreach ($preview['boxes'] as $box)
                    @if ($box['kind'] === 'image')
                        @if (!empty($box['src']))
                            <img src="{{ $box['src'] }}" alt="{{ $box['label'] }}" style="position:absolute;left:{{ $box['x'] / $preview['width'] * 100 }}%;top:{{ $box['y'] / $preview['height'] * 100 }}%;width:{{ $box['w'] / $preview['width'] * 100 }}%;height:{{ $box['h'] / $preview['height'] * 100 }}%;object-fit:fill;">
                        @endif
                    @else
                        <div style="position:absolute;left:{{ $box['x'] / $preview['width'] * 100 }}%;top:{{ $box['y'] / $preview['height'] * 100 }}%;width:{{ $box['w'] / $preview['width'] * 100 }}%;height:{{ $box['h'] / $preview['height'] * 100 }}%;overflow:hidden;">
                            @foreach ($box['lines'] as $line)
                                {{-- Font must scale with the sheet: shape geometry is expressed as a
                                     percentage of the preview width, so a fixed pt size would shrink
                                     relative to its box on narrow/printed output and clip. 1cqw is 1%
                                     of the preview width, which keeps text and boxes in the same
                                     proportion the workbook uses. --}}
                                <div style="text-align:{{ $line['align'] }};font-size:{{ round($line['style']['size'] * 4 / 3 / $preview['width'] * 100, 4) }}cqw;{{ $line['style']['bold'] ? 'font-weight:bold;' : '' }}color:#{{ $line['style']['color'] }};line-height:1.25;white-space:pre-wrap;">{{ $line['text'] }}</div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            {{-- Generic summary (cell-based templates) --}}
            <div class="doc-head">
                <h1>{{ $record->template->name ?? 'Document' }}</h1>
                <p>Patient Empanelment Document</p>
            </div>

            <div class="doc-section">
                <h2>Patient Information</h2>
                <div class="doc-grid">
                    <div class="field">
                        <label>Patient Name</label>
                        <div class="val">{{ $record->patient_name }}</div>
                    </div>
                    <div class="field">
                        <label>Birthdate</label>
                        <div class="val">{{ $record->birthdate?->format('m-d-y') }}</div>
                    </div>
                    @if ($record->appointment_date)
                        <div class="field">
                            <label>Date of Appointment</label>
                            <div class="val">{{ $record->appointment_date->format('m-d-y') }}</div>
                        </div>
                    @endif
                    @if ($record->auth_transaction_code)
                        <div class="field">
                            <label>Authorization Transaction Code</label>
                            <div class="val mono">{{ $record->auth_transaction_code }}</div>
                        </div>
                    @endif
                    @if ($record->pcu_error_code)
                        <div class="field">
                            <label>PCU Error Code</label>
                            <div class="val mono">{{ $record->pcu_error_code }}</div>
                        </div>
                    @endif
                    <div class="field">
                        <label>PhilHealth ID</label>
                        <div class="val mono">{{ $record->philhealth_id }}</div>
                    </div>
                    <div class="field">
                        <label>Head of Clinic</label>
                        <div class="val">{{ $record->head_of_clinic }}</div>
                    </div>
                    <div class="field">
                        <label>Date</label>
                        <div class="val">{{ ($record->date_today ?? now())->format('m-d-y') }}</div>
                    </div>
                </div>
            </div>

            <div class="img-grid">
                <div class="img-block">
                    <h3>ID Image</h3>
                    @if ($record->image_with_id_url)
                        <img src="{{ $record->image_with_id_url }}" alt="ID Image">
                    @else
                        <div class="img-empty">No ID image available</div>
                    @endif
                </div>

                <div class="img-block">
                    <h3>Empanelment Error Image</h3>
                    @if ($record->empanelment_error_image_url)
                        <img src="{{ $record->empanelment_error_image_url }}" alt="Empanelment Error">
                    @else
                        <div class="img-empty">No empanelment error image available</div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Screen-only provenance footer --}}
    <div class="doc-foot no-print">
        <span>Generated: {{ now()->format('F j, Y g:i A') }}</span>
        <span>By: {{ $record->creator->name ?? 'Unknown' }}</span>
        <span>Record ID: {{ $record->id }}</span>
    </div>

</div>
@endsection