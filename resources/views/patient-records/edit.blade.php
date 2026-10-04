@extends('layouts.app')

@section('page-title')
    <h2 style="font-size:20px;font-weight:600;">Edit Record: {{ $record->patient_name }}</h2>
@endsection

@section('content')
<div class="form-wrap">
    <p style="margin-bottom:1rem;"><a href="{{ route('records.show', $record) }}">&larr; View Record</a></p>

    <form method="POST" action="{{ route('records.update', $record) }}" enctype="multipart/form-data" id="record-form">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="card">
            <h3>Patient Information</h3>
            <p style="color:#6b7280;">Fields marked <span style="color:#b91c1c;">*</span> are required.</p>

            <div class="fields">
                <div class="field">
                    <label for="record_type">Record Type *</label>
                    <select name="record_type" id="record_type" required>
                        <option value="error" {{ old('record_type', $record->record_type ?? 'error') === 'error' ? 'selected' : '' }}>PCU Error</option>
                        <option value="success" {{ old('record_type', $record->record_type ?? 'error') === 'success' ? 'selected' : '' }}>PCU Success</option>
                    </select>
                    @error('record_type')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="patient_name">Patient Name *</label>
                    <input type="text" name="patient_name" id="patient_name" value="{{ old('patient_name', $record->patient_name) }}" required
                           placeholder="e.g. Juan Dela Cruz" autocomplete="off">
                    @error('patient_name')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="birthdate">Birthdate *</label>
                    <input type="date" name="birthdate" id="birthdate" value="{{ old('birthdate', $record->birthdate?->format('Y-m-d')) }}" required max="{{ now()->format('Y-m-d') }}">
                    @error('birthdate')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="philhealth_id">PhilHealth ID *</label>
                    <input type="text" name="philhealth_id" id="philhealth_id" value="{{ old('philhealth_id', $record->philhealth_id) }}" required
                           placeholder="12-345678901-2" maxlength="14" inputmode="numeric" autocomplete="off">
                    <p class="hint">Format 2-9-1 (12 digits), e.g. 12-345678901-2</p>
                    @error('philhealth_id')<p class="fielderror">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="pcu_error_code">PCU Error Code</label>
                    <input type="text" name="pcu_error_code" id="pcu_error_code" value="{{ old('pcu_error_code', $record->pcu_error_code) }}"
                           placeholder="e.g. PCU-ERR-001" autocomplete="off">
                    @error('pcu_error_code')<p class="fielderror">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

            <div class="form-col">
                <div class="card">
                    <h3>ID Image</h3>
                    <p style="color:#6b7280;">Keep the current image or replace it.</p>
                    @include('patient-records.partials.image-upload', [
                        'fieldName' => 'image_with_id',
                        'label' => 'ID Image',
                        'base64Field' => 'image_with_id_base64',
                        'existingImage' => $record->image_with_id_url,
                        'removeField' => 'remove_image_with_id',
                    ])
                </div>

                <div class="card">
                    <h3>Empanelment Error Image</h3>
                    <p style="color:#6b7280;">Keep the current image or replace it.</p>
                    @include('patient-records.partials.image-upload', [
                        'fieldName' => 'empanelment_error_image',
                        'label' => 'Empanelment Error Image',
                        'base64Field' => 'empanelment_error_image_base64',
                        'existingImage' => $record->empanelment_error_image_url,
                        'removeField' => 'remove_empanelment_error_image',
                    ])
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:1.25rem;">
            <input type="hidden" name="template_id" value="{{ old('template_id', $record->template_id) }}">
            <div class="actions" style="justify-content:flex-end;">
                <button type="submit" data-submit-btn><span data-btn-label>Save &amp; Print</span></button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var imageFieldReaders = {};
    var activeImageField = 'image_with_id';

    // Shows which drop zone a pasted image will land in. Mirrors
    // activeImageField so the highlight always reflects the real target.
    function syncPasteTarget() {
        var zones = document.querySelectorAll('[data-image-dropzone]');
        for (var i = 0; i < zones.length; i++) {
            var zone = zones[i];
            var isTarget = zone.getAttribute('data-image-dropzone') === activeImageField
                && !zone.classList.contains('locked');
            zone.classList.toggle('paste-target', isTarget);
        }
    }

    function setupImageField(fieldName, base64FieldName) {
        var dropZone = document.getElementById(fieldName + '-dropzone');
        var fileInput = document.getElementById(fieldName);
        var base64Input = document.getElementById(base64FieldName);
        var preview = document.getElementById(fieldName + '-preview');
        var previewImg = document.getElementById(fieldName + '-preview-img');
        var removeBtn = document.getElementById(fieldName + '-remove');
        if (!dropZone || !fileInput) return;

        function showPreview(dataUrl) {
            base64Input.value = dataUrl;
            previewImg.src = dataUrl;
            preview.classList.remove('hidden');
            refreshZoneState();
        }

        function refreshZoneState() {
            var hasImage = !preview.classList.contains('hidden');
            dropZone.classList.toggle('locked', hasImage);
            dropZone.setAttribute('aria-disabled', hasImage ? 'true' : 'false');
            dropZone.title = hasImage ? 'Remove the current image first to replace it' : '';
            fileInput.disabled = hasImage;
            syncPasteTarget();
        }

        function zoneLocked() {
            return !preview.classList.contains('hidden');
        }

        function validFile(file) {
            if (!file || file.type.indexOf('image/') !== 0) return false;
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) {
                alert('Invalid file type. Please use JPEG, PNG, or WebP.');
                return false;
            }
            if (file.size > 10 * 1024 * 1024) {
                alert('File is larger than 10MB.');
                return false;
            }
            return true;
        }

        function readFile(file) {
            if (!validFile(file)) return;
            var reader = new FileReader();
            reader.onload = function (e) { showPreview(e.target.result); };
            reader.readAsDataURL(file);
        }

        imageFieldReaders[fieldName] = readFile;
        refreshZoneState();

        dropZone.addEventListener('click', function (e) {
            activeImageField = fieldName;
            syncPasteTarget();
            if (zoneLocked()) return;
            if (e.target.closest('button')) return;
            fileInput.click();
        });
        dropZone.addEventListener('focus', function () { activeImageField = fieldName; syncPasteTarget(); });
        dropZone.addEventListener('mouseenter', function () { activeImageField = fieldName; syncPasteTarget(); });
        preview.addEventListener('mouseenter', function () { activeImageField = fieldName; syncPasteTarget(); });
        dropZone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
        });
        fileInput.addEventListener('change', function () {
            if (fileInput.files[0]) readFile(fileInput.files[0]);
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            dropZone.addEventListener(evt, function (e) {
                e.preventDefault();
                if (!zoneLocked()) dropZone.classList.add('armed');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dropZone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropZone.classList.remove('armed');
            });
        });
        dropZone.addEventListener('drop', function (e) {
            if (zoneLocked()) return;
            if (e.dataTransfer.files[0]) readFile(e.dataTransfer.files[0]);
        });

        function pasteHandler(e) {
            if (zoneLocked()) return false;
            var items = (e.clipboardData && e.clipboardData.items) || [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image/') === 0) {
                    var file = items[i].getAsFile();
                    if (file) { readFile(file); e.preventDefault(); return true; }
                }
            }
            return false;
        }
        dropZone.addEventListener('paste', pasteHandler);

        if (!window.__mcaPasteRouterBound) {
            window.__mcaPasteRouterBound = true;
            document.addEventListener('paste', function (e) {
                var tag = (document.activeElement && document.activeElement.tagName) || '';
                if (/INPUT|TEXTAREA|SELECT/.test(tag)) return;
                if (e.target && e.target.closest && e.target.closest('[data-image-dropzone]')) return;
                var pv = document.getElementById(activeImageField + '-preview');
                if (pv && !pv.classList.contains('hidden')) return;
                var read = imageFieldReaders[activeImageField] || imageFieldReaders['image_with_id'];
                if (!read) return;
                var items = (e.clipboardData && e.clipboardData.items) || [];
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image/') === 0) {
                        var file = items[i].getAsFile();
                        if (file) { read(file); e.preventDefault(); return; }
                    }
                }
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var flag = removeBtn.getAttribute('data-remove-field');
                if (flag) {
                    var input = document.getElementById(flag);
                    if (input) input.value = '1';
                }
                base64Input.value = '';
                fileInput.value = '';
                previewImg.src = '';
                preview.classList.add('hidden');
                dropZone.classList.remove('has-image');
                refreshZoneState();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupImageField('image_with_id', 'image_with_id_base64');
        setupImageField('empanelment_error_image', 'empanelment_error_image_base64');
            syncPasteTarget();

        var form = document.getElementById('record-form');
        if (form) {
            form.addEventListener('submit', function () {
                var btns = form.querySelectorAll('[data-submit-btn]');
                for (var i = 0; i < btns.length; i++) {
                    btns[i].disabled = true;
                    var label = btns[i].querySelector('[data-btn-label]');
                    if (label) label.textContent = 'Working…';
                }
            });
        }
    });
})();
</script>
@endpush
