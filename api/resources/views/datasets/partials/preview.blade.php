{{-- Static so the map survives detail.js re-rendering on each status poll. --}}
<section id="preview-section" class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white" aria-labelledby="preview-heading" hidden>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
        <h2 id="preview-heading" class="text-sm font-semibold text-slate-900">Preview</h2>
        <div id="preview-basemaps" class="inline-flex rounded-md ring-1 ring-slate-300 ring-inset" role="group" aria-label="Basemap" hidden>
            <button type="button" data-basemap="osm" aria-pressed="true"
                    class="rounded-l-md px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 aria-pressed:bg-slate-900 aria-pressed:text-white">Map</button>
            <button type="button" data-basemap="imagery" aria-pressed="false"
                    class="rounded-r-md px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 aria-pressed:bg-slate-900 aria-pressed:text-white">Imagery</button>
        </div>
    </div>

    {{-- Shown instead of the map while the dataset is queued, processing or failed. --}}
    <p id="preview-message" class="px-5 py-10 text-center text-sm text-slate-600" hidden></p>

    <div id="preview-body" hidden>
        <div class="relative">
            <div id="preview-map" class="h-[24rem] w-full bg-slate-100 sm:h-[32rem]"></div>

            <div id="preview-loading" class="absolute inset-0 grid place-items-center bg-white/60 text-sm text-slate-700" aria-live="polite">
                <span class="flex items-center gap-2 rounded-md bg-white px-3 py-2 shadow-sm ring-1 ring-slate-200">
                    <span class="size-4 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-700" aria-hidden="true"></span>
                    Loading preview…
                </span>
            </div>

            <div id="preview-error" class="absolute inset-x-4 top-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm" role="alert" hidden>
                <p class="font-semibold">Preview could not be loaded.</p>
                <p data-message class="mt-0.5"></p>
            </div>

            <div id="preview-popup" class="w-64 max-w-[80vw] rounded-lg bg-white text-sm shadow-lg ring-1 ring-slate-200" hidden>
                <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                    <p data-popup-title class="font-semibold text-slate-900"></p>
                    <button type="button" data-popup-close class="-mr-1 rounded p-1 text-slate-400 hover:text-slate-600" aria-label="Close">✕</button>
                </div>
                <div data-popup-body class="max-h-60 overflow-auto px-3 py-2"></div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-slate-200 px-5 py-3 text-sm">
            <label class="inline-flex items-center gap-2 text-slate-700">
                <input id="preview-layer-toggle" type="checkbox" checked disabled class="size-4 accent-emerald-700">
                Dataset layer
            </label>

            <label id="preview-opacity-wrap" class="inline-flex items-center gap-2 text-slate-700" hidden>
                Opacity
                <input id="preview-opacity" type="range" min="0" max="100" step="5" value="85" class="w-28 accent-emerald-700">
                <span id="preview-opacity-value" class="w-10 text-xs text-slate-500 tabular-nums">85%</span>
            </label>

            <div id="preview-legend" class="min-w-56" hidden>
                <p class="text-xs text-slate-500">Value (band 1, approximate range)</p>
                <div data-legend-bar class="mt-1 h-2.5 rounded-sm ring-1 ring-slate-200"></div>
                <div class="mt-0.5 flex justify-between text-xs text-slate-600 tabular-nums">
                    <span data-legend-min></span><span data-legend-max></span>
                </div>
            </div>

            <div id="preview-notices" class="w-full space-y-1 text-xs text-amber-800" aria-live="polite" hidden></div>
        </div>
    </div>
</section>
