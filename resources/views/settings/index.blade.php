@extends('layouts.app')

@section('page-title')
    <h2 style="font-size:20px;font-weight:600;">Settings</h2>
@endsection

@section('content')
<div>
    <div class="card">
        <h3>Facility Information</h3>

        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label for="facility_name">Facility Name</label>
                <input type="text" name="facility_name" id="facility_name" value="{{ old('facility_name', \App\Models\Setting::getFacilityName()) }}">
                <p class="hint">Facility name shown on generated documents.</p>
                @error('facility_name')<p class="fielderror">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="head_of_clinic">Head of Clinic *</label>
                <input type="text" name="head_of_clinic" id="head_of_clinic" value="{{ old('head_of_clinic', \App\Models\Setting::getHeadOfClinic()) }}" required>
                <p class="hint">Used as the default Head of Clinic in new records and generated documents.</p>
                @error('head_of_clinic')<p class="fielderror">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="facility_address">Facility Address</label>
                <textarea name="facility_address" id="facility_address" rows="2">{{ old('facility_address', \App\Models\Setting::getFacilityAddress()) }}</textarea>
                <p class="hint">Used by <code>@{{institution_address}}</code> / <code>@{{facility_address}}</code> placeholders.</p>
            </div>

            <h3 style="margin-top:1.5rem;">Excel Templates</h3>
            <p class="hint" style="margin-bottom:1rem;">
                Each printing record type keeps its own layout. {{ \App\Support\RecordType::label('success') }} records
                are data-only, so they print nothing and have no template.
            </p>

            {{-- One upload slot per printing type. Each save only touches the
                 types that actually received a file. --}}
            @foreach (\App\Support\RecordType::templateTypes() as $type)
                @php $current = $templates[$type] ?? null; @endphp
                <div style="border:1px solid var(--line);border-radius:var(--radius);padding:1rem 1.1rem;margin-bottom:1rem;">
                    <h4 style="margin:0 0 .35rem;font-size:14px;">{{ \App\Support\RecordType::label($type) }} Template</h4>

                    @if ($current)
                        <p style="margin:0 0 .75rem;color:#6b7280;">
                            Current: <strong>{{ $current->name }}</strong>
                            <span>v{{ $current->version }}</span>
                        </p>
                    @else
                        <p style="margin:0 0 .75rem;color:#b45309;">
                            No {{ \App\Support\RecordType::label($type) }} template uploaded yet.
                            {{ \App\Support\RecordType::label($type) }} records cannot be added until one is.
                        </p>
                    @endif

                    <div class="field">
                        <label for="template_file_{{ $type }}">
                            {{ $current ? 'Replace' : 'Upload' }} {{ \App\Support\RecordType::label($type) }} Template (.xlsx)
                        </label>
                        <input type="file" name="template_file_{{ $type }}" id="template_file_{{ $type }}" accept=".xlsx">
                        <p class="hint">
                            Leave empty to keep the current file. Uploading makes this the live
                            {{ \App\Support\RecordType::label($type) }} template and moves that type's records onto it.
                        </p>
                        @error('template_file_' . $type)<p class="fielderror">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endforeach

            <div class="actions" style="justify-content:flex-end;">
                <button type="submit">Save Settings</button>
            </div>
        </form>
    </div>

    @foreach (\App\Support\RecordType::templateTypes() as $type)
        @php $rows = $fields[$type] ?? null; @endphp
        @if ($rows && $rows->total() > 0)
        <div class="card">
            <div class="page-head" style="margin-bottom:.5rem;">
                <h3>{{ \App\Support\RecordType::label($type) }} Placeholders</h3>
                <span class="hint">{{ $rows->total() }} total</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Placeholder</th><th>Type</th><th>Required</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $field)
                            <tr>
                                <td><code>{{ $field->placeholder }}</code></td>
                                <td>{{ ucfirst($field->type) }}</td>
                                <td>
                                    @if ($field->is_required)
                                        <span class="badge badge-green">Required</span>
                                    @else
                                        <span class="badge">Optional</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $rows->onEachSide(1)->links() }}
        </div>
        @endif
    @endforeach
</div>
@endsection
