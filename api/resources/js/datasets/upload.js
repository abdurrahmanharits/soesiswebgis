import { uploadDataset } from './api.js';
import { escapeHtml, formatBytes } from './format.js';

// Mirrors the backend rule `max:524288` (kilobytes) in DatasetController@store.
export const MAX_UPLOAD_BYTES = 512 * 1024 * 1024;

const FORMAT_BY_EXTENSION = { zip: 'Shapefile (ZIP)', tif: 'GeoTIFF', tiff: 'GeoTIFF' };

function extensionOf(name) {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

function validate({ title, file }) {
    const errors = {};

    if (!title.trim()) {
        errors.title = 'Title is required.';
    }

    if (!file) {
        errors.file = 'Choose a file to upload.';
    } else if (!FORMAT_BY_EXTENSION[extensionOf(file.name)]) {
        errors.file = 'Unsupported file type. Use a Shapefile .zip, or a .tif / .tiff GeoTIFF.';
    } else if (file.size > MAX_UPLOAD_BYTES) {
        errors.file = `File is ${formatBytes(file.size)}. Maximum file size is 512 MB.`;
    }

    return errors;
}

/**
 * Wires the <dialog id="upload-dialog"> markup in datasets/index.blade.php.
 * Calls onUploaded(dataset, message) after the backend accepts the file (HTTP 202).
 */
export function initUploadDialog({ onUploaded }) {
    const dialog = document.getElementById('upload-dialog');
    const form = document.getElementById('upload-form');
    const titleInput = document.getElementById('upload-title');
    const fileInput = document.getElementById('upload-file');
    const dropzone = document.getElementById('upload-dropzone');
    const fileInfo = document.getElementById('upload-file-info');
    const titleError = document.getElementById('upload-title-error');
    const fileError = document.getElementById('upload-file-error');
    const formError = document.getElementById('upload-form-error');
    const progress = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressText = document.getElementById('upload-progress-text');
    const submitButton = document.getElementById('upload-submit');
    const cancelButton = document.getElementById('upload-cancel');
    const closeButton = document.getElementById('upload-close');

    let selectedFile = null;
    let currentUpload = null;

    function setFieldError(element, input, message) {
        element.textContent = message || '';
        element.hidden = !message;
        input?.setAttribute('aria-invalid', message ? 'true' : 'false');
    }

    function showErrors(errors = {}, message = '') {
        setFieldError(titleError, titleInput, errors.title);
        setFieldError(fileError, fileInput, errors.file);
        formError.hidden = !message;
        formError.querySelector('[data-message]').textContent = message;
    }

    function renderFile() {
        if (!selectedFile) {
            fileInfo.hidden = true;
            fileInfo.innerHTML = '';
            return;
        }

        const kind = FORMAT_BY_EXTENSION[extensionOf(selectedFile.name)] ?? 'Unsupported type';

        fileInfo.hidden = false;
        fileInfo.innerHTML = `
            <span class="min-w-0 truncate font-medium text-slate-800">${escapeHtml(selectedFile.name)}</span>
            <span class="shrink-0 text-slate-500">${escapeHtml(kind)} · ${formatBytes(selectedFile.size)}</span>`;
    }

    function setFile(file) {
        selectedFile = file ?? null;
        renderFile();

        if (selectedFile) {
            setFieldError(fileError, fileInput, validate({ title: 'x', file: selectedFile }).file);
        }
    }

    function setBusy(busy) {
        form.toggleAttribute('data-busy', busy);
        titleInput.disabled = busy;
        fileInput.disabled = busy;
        dropzone.classList.toggle('pointer-events-none', busy);
        dropzone.classList.toggle('opacity-60', busy);
        submitButton.disabled = busy;
        closeButton.disabled = busy;
        cancelButton.textContent = busy ? 'Cancel upload' : 'Cancel';
        progress.hidden = !busy;
    }

    function setProgress(fraction) {
        const percent = Math.round(fraction * 100);

        progressBar.style.width = `${percent}%`;
        progress.querySelector('[role="progressbar"]').setAttribute('aria-valuenow', String(percent));
        progressText.textContent = fraction >= 1
            ? 'Upload complete. Registering dataset…'
            : `Uploading… ${percent}%`;
    }

    function reset() {
        form.reset();
        setFile(null);
        showErrors();
        setProgress(0);
        setBusy(false);
    }

    function open() {
        reset();
        dialog.showModal();
        titleInput.focus();
    }

    function close() {
        if (currentUpload) return;
        dialog.close();
    }

    document.querySelectorAll('[data-open-upload]').forEach((button) => {
        button.addEventListener('click', open);
    });

    closeButton.addEventListener('click', close);

    cancelButton.addEventListener('click', () => {
        if (currentUpload) {
            currentUpload.abort();
        } else {
            close();
        }
    });

    // Esc must not silently drop an in-flight upload.
    dialog.addEventListener('cancel', (event) => {
        if (currentUpload) event.preventDefault();
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });

    fileInput.addEventListener('change', () => setFile(fileInput.files[0]));

    ['dragenter', 'dragover'].forEach((type) => {
        dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            dropzone.dataset.dragging = 'true';
        });
    });

    ['dragleave', 'drop'].forEach((type) => {
        dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            delete dropzone.dataset.dragging;
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const file = event.dataTransfer?.files?.[0];
        if (file) setFile(file);
    });

    titleInput.addEventListener('input', () => setFieldError(titleError, titleInput, ''));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const values = { title: titleInput.value, file: selectedFile };
        const errors = validate(values);

        if (Object.keys(errors).length > 0) {
            showErrors(errors);
            (errors.title ? titleInput : fileInput).focus();
            return;
        }

        showErrors();
        setBusy(true);
        setProgress(0);

        currentUpload = uploadDataset(
            { title: values.title.trim(), file: values.file },
            { onProgress: setProgress },
        );

        try {
            const body = await currentUpload.promise;

            currentUpload = null;
            dialog.close();
            onUploaded(body.data, body.message);
        } catch (error) {
            currentUpload = null;
            setBusy(false);

            if (error.status === -1) {
                showErrors({}, 'Upload cancelled. No dataset was created.');
                return;
            }

            const fieldErrors = {
                title: error.errors?.title?.[0],
                file: error.errors?.file?.[0],
            };

            // Backend returns the format error as a top-level 422 message.
            const message = fieldErrors.title || fieldErrors.file ? '' : error.message;

            showErrors(fieldErrors, message);
        }
    });

    return { open };
}
