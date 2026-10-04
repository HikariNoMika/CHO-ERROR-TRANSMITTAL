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

            <h3 style="margin-top:1.5rem;">Excel Template</h3>
            @if ($currentTemplate)
                <p>Current: <strong>{{ $currentTemplate->name }}</strong> <span style="color:#6b7280;">v{{ $currentTemplate->version }}</span></p>
            @else
                <p style="color:#6b7280;">No active template yet.</p>
            @endif

            <div class="field">
                <label for="template_file">Replace Template (.xlsx)</label>
                <input type="file" name="template_file" id="template_file" accept=".xlsx">
                <p class="hint">Leave empty to keep the current file. Uploading replaces it as the single active template (version auto-bumped).</p>
                @error('template_file')<p class="fielderror">{{ $message }}</p>@enderror
            </div>

            <div class="actions" style="justify-content:flex-end;">
                <button type="submit">Save Settings</button>
            </div>
        </form>
    </div>

    @if ($fields && $fields->total() > 0)
    <div class="card">
        <div class="page-head" style="margin-bottom:.5rem;">
            <h3>Detected Placeholders</h3>
            <span class="hint">{{ $fields->total() }} total</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Placeholder</th><th>Type</th><th>Required</th></tr>
                </thead>
                <tbody>
                    @foreach ($fields as $field)
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
        {{ $fields->links() }}
    </div>
    @endif
</div>
@endsection
