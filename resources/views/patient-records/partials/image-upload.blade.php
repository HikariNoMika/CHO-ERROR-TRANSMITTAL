<div class="image-upload">
    <input type="hidden" name="{{ $base64Field }}" id="{{ $base64Field }}">
    <input type="hidden" name="{{ $removeField }}" id="{{ $removeField }}" value="0">

    <div id="{{ $fieldName }}-preview" class="{{ $existingImage ? '' : 'hidden' }}" style="margin-bottom:.75rem;">
        <img id="{{ $fieldName }}-preview-img" src="{{ $existingImage ?? '' }}" alt="{{ $label }}" class="preview" style="display:block;">
        <div style="margin-top:.5rem;">
            <button type="button" id="{{ $fieldName }}-remove" data-remove-field="{{ $removeField }}" class="btn-danger">Remove</button>
        </div>
    </div>

    <div id="{{ $fieldName }}-dropzone" data-image-dropzone="{{ $fieldName }}" class="dropzone" tabindex="0" role="button" aria-label="Upload {{ $label }}">
        <div>
            <p>Paste image here <kbd>Ctrl + V</kbd></p>
            <p style="color:#6b7280;font-size:13px;">or</p>
            <span class="btn-primary" style="pointer-events:none;">Upload Image</span>
            <input type="file" name="{{ $fieldName }}" id="{{ $fieldName }}" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1">
            <p class="hint">Drag &amp; drop, paste, or click to browse — JPEG, PNG, WebP up to 10MB</p>
        </div>
    </div>

    @error($fieldName)
        <p class="fielderror">{{ $message }}</p>
    @enderror
</div>
