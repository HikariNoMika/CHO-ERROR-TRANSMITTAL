@extends('layouts.app')

@section('page-title')
    <h2 class="page-title">{{ \App\Support\RecordType::plural(request('type')) }}</h2>
@endsection

@section('content')
@php
    $type = \App\Support\RecordType::normalise(request('type'));
    // Success records only ever carry the four log fields (name, birthdate,
    // PIN, success code), so the table drops the error-only columns. Medical
    // mission records keep the error layout, minus the PCU code column.
    $isSuccess = $type === \App\Support\RecordType::SUCCESS;
    $hasErrorCode = $type !== \App\Support\RecordType::MISSION;
    // pick, name, birthdate, PhilHealth, created, actions; then the optional
    // head-of-clinic and type columns (both hidden for success) and the code
    // column (absent for medical mission).
    $columnCount = 6 + ($isSuccess ? 0 : 2) + ($hasErrorCode ? 1 : 0);

@endphp

@if ($errors->has('export'))
        <div class="bulk-result is-error">{{ $errors->first('export') }}</div>
    @endif
    <div class="actions page-actions">
        <a href="{{ route('records.export', array_merge(request()->query(), ['type' => $type])) }}" class="btn-secondary">Export Excel</a>
        <a href="{{ \App\Support\RecordType::createUrl($type) }}" class="btn-primary">+ New Record</a>
    </div>

    <div class="card sticky-toolbar" id="record-toolbar">
        <form method="GET" id="filter-form">
            <input type="hidden" name="type" value="{{ \App\Support\RecordType::normalise(request('type')) }}">
            <div class="filter-row">
                <div class="field grow">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Patient name, PhilHealth ID, code...">
                </div>
                <div class="field">
                    <label for="date_from">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}">
                </div>
                <div class="field">
                    <label for="date_to">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}">
                </div>
                <div class="field rows">
                    <label for="per_page">Rows</label>
                    <select name="per_page" id="per_page" onchange="this.form.submit()">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="actions toolbar-actions">
                    <a href="{{ \App\Support\RecordType::indexUrl($type) }}" class="btn-secondary">Clear</a>
                    <button type="submit">Filter</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Deliberately wraps ONLY the action bar, never the table. Every row already
         contains its own Delete form, and <form> inside <form> is invalid HTML
         that browsers silently drop. Row checkboxes therefore sit outside this
         form; JS copies the ticked record ids into #bulk-targets on submit. --}}
    <form method="POST" action="{{ route('records.bulk-export') }}" id="bulk-form" class="bulk-bar">
        @csrf
        <div id="bulk-targets"></div>
        {{-- The ticked rows came from this filtered list, so the download needs the
             same window in order to state the range it covers. --}}
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">

        <label class="bulk-selectall only-mobile">
            <input type="checkbox" class="select-all-toggle"> <span>Select all</span>
        </label>
        <div class="bulk-actions">
            <span class="bulk-count"><strong id="bulk-count">0</strong> selected</span>
            <button type="button" class="btn-secondary" id="bulk-clear" disabled>Clear</button>
            <button type="submit" class="btn-primary" id="bulk-go" disabled>Export selected</button>
        </div>
    </form>

    <div class="table-wrap only-desktop">
        <table>
            <thead>
                <tr>
                    <th class="pick-col">
                        <input type="checkbox" class="select-all-toggle" aria-label="Select all records on this page">
                    </th>
                    <th>Patient Name</th>
                    <th>Birthdate</th>
                    <th>PhilHealth ID</th>
                    @unless ($isSuccess)
                        <th class="hide-below-xl">Head of Clinic</th>
                    @endunless
                    @if ($hasErrorCode)
                        <th>{{ $isSuccess ? 'PCU Success Code' : 'PCU Error Code' }}</th>
                    @endif
                    @unless ($isSuccess)
                        <th>Type</th>
                    @endunless
                    <th class="hide-below-xl">Created</th>
                    <th class="align-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="pick-col">
                            {{-- Every record is selectable, whatever its status; the export is
                                 read-only and ignores document state. --}}
<input type="checkbox" value="{{ $record->id }}" class="row-pick" aria-label="Select {{ $record->patient_name }}">
                    </td>
                    <td><strong>{{ $record->patient_name }}</strong></td>
                        <td>{{ $record->birthdate?->format('M j, Y') ?? '—' }}</td>
                        <td><span class="mono">{{ $record->philhealth_id }}</span></td>
                        @unless ($isSuccess)
                            <td class="hide-below-xl">{{ $record->head_of_clinic }}</td>
                        @endunless
                        @if ($hasErrorCode)
                            <td><code>{{ $record->pcu_error_code ?? '—' }}</code></td>
                        @endif
                        @unless ($isSuccess)
                            <td><span class="badge {{ \App\Support\RecordType::badge($record->record_type) }}">{{ \App\Support\RecordType::label($record->record_type) }}</span></td>
                        @endunless
                        <td class="hide-below-xl">{{ $record->created_at->format('M j, Y g:i A') }}</td>
                        <td class="align-right">
                            <span class="row-actions">
                                <a href="{{ route('records.show', $record) }}">View</a>
                                @can('update', $record)
                                    <a href="{{ route('records.edit', $record) }}">Edit</a>
                                @endcan
                                @can('generate', $record)
                                    @if ($record->status !== 'draft')
                                        {{-- Generate/Download moved to the bulk action bar;
                                             Print stays for records that already have a document. --}}
                                        <a href="{{ route('records.print', $record) }}?print=1" target="_blank">Print</a>
                                    @endif
                                @endcan
                                @can('delete', $record)
                                    <form action="{{ route('records.destroy', $record) }}" method="POST" onsubmit="return confirm('Delete this record?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger">Delete</button>
                                    </form>
                                @endcan
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $columnCount }}" class="table-empty">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="only-mobile">
        @forelse ($records as $record)
            <div class="card">
                <div class="page-head card-head">
                    <input type="checkbox" value="{{ $record->id }}" class="row-pick" aria-label="Select {{ $record->patient_name }}">
                    <strong>{{ $record->patient_name }}</strong>
                    @unless ($isSuccess)
                        <span class="badge {{ \App\Support\RecordType::badge($record->record_type) }}">{{ \App\Support\RecordType::label($record->record_type) }}</span>
                    @endunless
                    <span class="badge">{{ ucfirst($record->status) }}</span>
                </div>
                <div class="meta-line">
                    {{ $record->birthdate?->format('M j, Y') ?? '—' }} · <span class="mono">{{ $record->philhealth_id }}</span>
                    @if ($hasErrorCode)
                        · {{ $isSuccess ? 'Success Code' : 'PCU' }}: <code>{{ $record->pcu_error_code ?? '—' }}</code>
                    @endif
                </div>
                <div>
                    <span class="row-actions">
                        <a href="{{ route('records.show', $record) }}">View</a>
                        @can('update', $record)
                            <a href="{{ route('records.edit', $record) }}">Edit</a>
                        @endcan
                        @can('generate', $record)
                            @if ($record->status !== 'draft')
                                <a href="{{ route('records.print', $record) }}?print=1" target="_blank">Print</a>
                            @endif
                        @endcan
                    </span>
                </div>
            </div>
        @empty
            <div class="card table-empty">No records found.</div>
        @endforelse
    </div>

    {{ $records->links() }}
@endsection

@push('scripts')
<script>
    // Lift the filter bar off the page once it reaches the navbar, so it reads
    // as a floating toolbar rather than a card sitting mid-content.
    (function () {
        var bar = document.getElementById('record-toolbar');
        if (!bar) return;

        var nav = document.querySelector('.navbar');
        var navHeight = function () {
            return nav ? nav.getBoundingClientRect().height : 54;
        };

        function update() {
            if (window.matchMedia('(max-width: 767.98px)').matches) {
                bar.classList.remove('is-stuck');
                return;
            }
            bar.classList.toggle('is-stuck', bar.getBoundingClientRect().top <= navHeight() + 1);
        }

        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    })();

    // Bulk selection: export the ticked records to one workbook.
    (function () {
        var form = document.getElementById('bulk-form');
        if (!form) return;

        var targets = document.getElementById('bulk-targets');
        var all = Array.prototype.slice.call(document.querySelectorAll('.row-pick'));
        var toggles = Array.prototype.slice.call(document.querySelectorAll('.select-all-toggle'));
        var count = document.getElementById('bulk-count');
        var go = document.getElementById('bulk-go');
        var clear = document.getElementById('bulk-clear');

        // The table and the mobile card list both render a checkbox per record,
        // and only one of the two is visible at a time. Every operation is scoped
        // to the visible ones, otherwise select-all would tick both copies of a
        // record and post each id twice.
        function visible() {
            return all.filter(function (p) { return p.offsetParent !== null; });
        }

        // Everything currently ticked, de-duplicated. The export is read-only, so
        // every ticked row is included whatever state its document is in.
        function selectedIds() {
            var seen = {};
            visible().forEach(function (p) {
                if (p.checked) { seen[p.value] = true; }
            });
            return Object.keys(seen);
        }

        function sync() {
            var ids = selectedIds();
            if (count) count.textContent = ids.length;
            if (go) {
                go.disabled = ids.length === 0;
                go.textContent = 'Export selected';
            }
            if (clear) clear.disabled = ids.length === 0;

            var vis = visible();
            // Reflect state only when partly ticked, so a second click completes
            // the selection instead of clearing it.
            toggles.forEach(function (t) {
                t.checked = ids.length > 0 && ids.length === vis.length;
                t.indeterminate = ids.length > 0 && ids.length < vis.length;
            });
        }

        toggles.forEach(function (t) {
            t.addEventListener('change', function () {
                var on = t.checked;
                visible().forEach(function (p) { p.checked = on; });
                sync();
            });
        });

        all.forEach(function (p) {
            p.addEventListener('change', sync);
        });

        if (clear) {
            clear.addEventListener('click', function () {
                all.forEach(function (p) { p.checked = false; });
                sync();
            });
        }

        function resetBusy() {
            if (go) {
                go.textContent = 'Export selected';
                go.disabled = selectedIds().length === 0;
            }
            if (clear) clear.disabled = true;
            document.body.classList.remove('is-busy');
        }

        function setBusy(busy, ids) {
            var many = function (n, one, many_) { return n + ' ' + (n === 1 ? one : many_); };
            if (go) {
                go.disabled = busy || selectedIds().length === 0;
                go.textContent = busy
                    ? 'Exporting ' + many(ids.length, 'record', 'records') + '...'
                    : 'Export selected';
            }
            if (clear) clear.disabled = busy || selectedIds().length === 0;
            document.body.classList.toggle('is-busy', busy);
        }

        // The response is a file download, which never unloads the page. A plain
        // form POST would therefore leave the bar disabled with "Exporting..."
        // forever, so the submit is taken over: fetch the workbook, save it from a
        // blob, and always hand the page back.
        function filenameFrom(disposition, fallback) {
            var star = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(disposition || '');
            if (star) {
                try { return decodeURIComponent(star[1].replace(/["']/g, '')); } catch (e) { /* fall through */ }
            }
            var plain = /filename="?([^";]+)"?/i.exec(disposition || '');
            return plain ? plain[1] : fallback;
        }

        function saveBlob(blob, filename) {
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = filename || 'records.xlsx';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 30000);
        }

        // Named, not anonymous, so the failure path can detach it before the
        // browser takes over the submission.
        function onSubmit(e) {
            var ids = selectedIds();

            if (ids.length === 0) {
                e.preventDefault();
                return;
            }

            var many = function (n, one, many_) { return n + ' ' + (n === 1 ? one : many_); };
            var lines = [
                'Export ' + many(ids.length, 'record', 'records') + ' to Excel?',
                '',
                'The selected records go into one spreadsheet, one row each.',
                'Nothing is generated and no record is changed.'
            ];

            if (!window.confirm(lines.join('\n'))) {
                e.preventDefault();
                return;
            }

            // Checkboxes live outside the form (to avoid nesting it inside the
            // per-row Delete forms), so mirror the selection into hidden inputs.
            targets.innerHTML = '';
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'records[]';
                input.value = id;
                targets.appendChild(input);
            });

            e.preventDefault();
            setBusy(true, ids);

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                var type = res.headers.get('Content-Type') || '';
                var isWorkbook = res.ok && type.indexOf('spreadsheetml') !== -1;

                if (!isWorkbook) {
                    // A validation failure redirects back with errors, so hand the
                    // request to the browser and let the page render the message.
                    form.removeEventListener('submit', onSubmit);
                    form.submit();
                    return null;
                }

                return res.blob().then(function (blob) {
                    saveBlob(blob, filenameFrom(res.headers.get('Content-Disposition'), 'MCA_Records_Selected.xlsx'));
                });
            }).catch(function () {
                window.alert('The export could not be started. Check your connection and try again.');
            }).then(function () {
                targets.innerHTML = '';
                resetBusy();
            });
        }

        form.addEventListener('submit', onSubmit);

        // Coming back via the back/forward cache would otherwise restore the page
        // with the button still stuck on "Exporting...".
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                resetBusy();
            }
        });

        sync();
    })();
</script>
@endpush
