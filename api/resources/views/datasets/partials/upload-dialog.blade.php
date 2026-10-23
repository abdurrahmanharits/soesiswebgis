<dialog id="upload-dialog" aria-labelledby="upload-heading"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl bg-white p-0 shadow-xl backdrop:bg-slate-900/40">
    <form id="upload-form" novalidate>
        <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
            <div>
                <h2 id="upload-heading" class="text-base font-semibold">Upload dataset</h2>
                <p class="mt-0.5 text-sm text-slate-500">Files are processed in the background after upload.</p>
            </div>
            <button id="upload-close" type="button" class="-mr-2 rounded-md p-2 text-slate-400 hover:text-slate-600 disabled:opacity-40" aria-label="Close">
                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
            </button>
        </div>

        <div class="space-y-5 px-6 py-5">
            <div id="upload-form-error" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" hidden>
                <p data-message></p>
            </div>

            <div>
                <label for="upload-title" class="block text-sm font-medium text-slate-800">Title <span class="text-rose-600" aria-hidden="true">*</span></label>
                <input id="upload-title" name="title" type="text" required maxlength="255" autocomplete="off"
                       placeholder="e.g. Batas Desa Kab. Sintang 2025" aria-describedby="upload-title-error"
                       class="mt-1.5 w-full rounded-md border-0 px-3 py-2 text-sm ring-1 ring-slate-300 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-600 aria-[invalid=true]:ring-rose-400 disabled:bg-slate-50">
                <p id="upload-title-error" class="mt-1.5 text-sm text-rose-700" hidden></p>
            </div>

            <div>
                <span class="block text-sm font-medium text-slate-800">File <span class="text-rose-600" aria-hidden="true">*</span></span>
                <label id="upload-dropzone" for="upload-file"
                       class="mt-1.5 flex cursor-pointer flex-col items-center rounded-lg border-2 border-dashed border-slate-300 px-4 py-6 text-center hover:border-emerald-500 hover:bg-emerald-50/40 data-[dragging=true]:border-emerald-500 data-[dragging=true]:bg-emerald-50">
                    <svg class="size-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                    <span class="mt-2 text-sm"><span class="font-medium text-emerald-700">Choose a file</span> or drag it here</span>
                    <span class="mt-1 text-xs text-slate-500">.zip, .tif, .tiff · Maximum file size: 512 MB</span>
                </label>
                <input id="upload-file" name="file" type="file" accept=".zip,.tif,.tiff" class="sr-only" aria-describedby="upload-file-error upload-help">
                <div id="upload-file-info" class="mt-2 flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" hidden></div>
                <p id="upload-file-error" class="mt-1.5 text-sm text-rose-700" hidden></p>
            </div>

            <div id="upload-help" class="rounded-md bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                <p class="font-medium text-slate-700">Supported:</p>
                <ul class="mt-1 list-disc pl-4">
                    <li>Shapefile (.zip containing .shp, .shx, .dbf, .prj). ZIP must contain exactly one .shp file.</li>
                    <li>GeoTIFF (.tif, .tiff)</li>
                </ul>
                <p class="mt-1">Maximum file size: 512 MB.</p>
            </div>

            <div id="upload-progress" hidden>
                <div class="flex items-center justify-between text-sm">
                    <span id="upload-progress-text" class="text-slate-700" aria-live="polite">Uploading…</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div id="upload-progress-bar" class="h-full w-0 rounded-full bg-emerald-600 transition-[width] duration-200"></div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <button id="upload-cancel" type="button" class="rounded-md bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Cancel</button>
            <button id="upload-submit" type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60">Upload</button>
        </div>
    </form>
</dialog>
