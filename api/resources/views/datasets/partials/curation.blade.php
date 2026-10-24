@php
    $input = 'mt-1 w-full rounded-md border-0 px-3 py-2 text-sm ring-1 ring-slate-300 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-600 disabled:bg-slate-50 disabled:text-slate-700 aria-[invalid=true]:ring-rose-400';
    $label = 'block text-sm font-medium text-slate-800';
    $required = '<span class="text-rose-600" title="Required for approval">*</span>';
    $error = 'mt-1 text-xs text-rose-700';
@endphp

{{-- Static so typed values survive detail.js re-rendering. Wired by resources/js/datasets/curation.js. --}}
<section id="curation-section" class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white" aria-labelledby="curation-heading" hidden>
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
        <h2 id="curation-heading" class="text-sm font-semibold text-slate-900">Curation</h2>
        <span id="curation-badge"></span>
    </div>

    <p id="curation-message" class="px-5 py-8 text-center text-sm text-slate-600" hidden></p>

    <div id="curation-body" class="grid lg:grid-cols-3" hidden>
        <form id="curation-form" class="space-y-6 px-5 py-5 lg:col-span-2" novalidate>
            <p id="curation-locked" class="rounded-md bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-200" hidden>
                Metadata is locked. Reopen the dataset to make changes.
            </p>

            <fieldset class="space-y-4">
                <legend class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Identification</legend>
                <div>
                    <label for="cur-title" class="{{ $label }}">Title {!! $required !!}</label>
                    <input id="cur-title" name="title" type="text" maxlength="255" class="{{ $input }}">
                    <p data-error-for="title" class="{{ $error }}" hidden></p>
                </div>
                <div>
                    <label for="cur-description" class="{{ $label }}">Description {!! $required !!}</label>
                    <textarea id="cur-description" name="description" rows="3" maxlength="5000" class="{{ $input }}" placeholder="What the dataset contains and what it is for."></textarea>
                    <p data-error-for="description" class="{{ $error }}" hidden></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="cur-keywords" class="{{ $label }}">Keywords</label>
                        <input id="cur-keywords" name="keywords" type="text" class="{{ $input }}" placeholder="batas desa, administrasi">
                        <p class="mt-1 text-xs text-slate-500">Separate with commas.</p>
                        <p data-error-for="keywords" class="{{ $error }}" hidden></p>
                    </div>
                    <div>
                        <label for="cur-topic" class="{{ $label }}">Topic category</label>
                        <select id="cur-topic" name="topic_category" class="{{ $input }}"><option value="">—</option></select>
                        <p data-error-for="topic_category" class="{{ $error }}" hidden></p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Source &amp; rights</legend>
                <div>
                    <label for="cur-source" class="{{ $label }}">Source {!! $required !!}</label>
                    <textarea id="cur-source" name="source" rows="2" maxlength="1000" class="{{ $input }}" placeholder="Original data source or reference."></textarea>
                    <p data-error-for="source" class="{{ $error }}" hidden></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="cur-org" class="{{ $label }}">Source organization</label>
                        <input id="cur-org" name="source_organization" type="text" maxlength="255" class="{{ $input }}">
                        <p data-error-for="source_organization" class="{{ $error }}" hidden></p>
                    </div>
                    <div>
                        <label for="cur-license" class="{{ $label }}">License {!! $required !!}</label>
                        <select id="cur-license" name="license" class="{{ $input }}"><option value="">—</option></select>
                        <p data-error-for="license" class="{{ $error }}" hidden></p>
                    </div>
                </div>
                <div id="cur-license-other-wrap" hidden>
                    <label for="cur-license-other" class="{{ $label }}">License name {!! $required !!}</label>
                    <input id="cur-license-other" name="license_other" type="text" maxlength="255" class="{{ $input }}">
                    <p data-error-for="license_other" class="{{ $error }}" hidden></p>
                </div>
                <div>
                    <label for="cur-usage" class="{{ $label }}">Usage constraints</label>
                    <textarea id="cur-usage" name="usage_constraints" rows="2" maxlength="2000" class="{{ $input }}"></textarea>
                    <p data-error-for="usage_constraints" class="{{ $error }}" hidden></p>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Lineage &amp; extent</legend>
                <div>
                    <label for="cur-lineage" class="{{ $label }}">Lineage</label>
                    <textarea id="cur-lineage" name="lineage" rows="2" maxlength="5000" class="{{ $input }}" placeholder="How the data was produced and processed."></textarea>
                    <p data-error-for="lineage" class="{{ $error }}" hidden></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="cur-start" class="{{ $label }}">Temporal start</label>
                        <input id="cur-start" name="temporal_start" type="date" class="{{ $input }}">
                        <p data-error-for="temporal_start" class="{{ $error }}" hidden></p>
                    </div>
                    <div>
                        <label for="cur-end" class="{{ $label }}">Temporal end</label>
                        <input id="cur-end" name="temporal_end" type="date" class="{{ $input }}">
                        <p data-error-for="temporal_end" class="{{ $error }}" hidden></p>
                    </div>
                    <div>
                        <label for="cur-geo" class="{{ $label }}">Geographic description</label>
                        <input id="cur-geo" name="geographic_description" type="text" maxlength="255" class="{{ $input }}" placeholder="e.g. Kab. Aceh Barat">
                        <p data-error-for="geographic_description" class="{{ $error }}" hidden></p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Contact</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="cur-contact-name" class="{{ $label }}">Name</label>
                        <input id="cur-contact-name" name="contact_name" type="text" maxlength="255" class="{{ $input }}">
                        <p data-error-for="contact_name" class="{{ $error }}" hidden></p>
                    </div>
                    <div>
                        <label for="cur-contact-email" class="{{ $label }}">Email</label>
                        <input id="cur-contact-email" name="contact_email" type="email" maxlength="255" class="{{ $input }}">
                        <p data-error-for="contact_email" class="{{ $error }}" hidden></p>
                    </div>
                </div>
            </fieldset>

            <div class="flex items-center gap-3">
                <button id="curation-save" type="submit" class="rounded-md bg-white px-4 py-2 text-sm font-medium text-slate-800 ring-1 ring-slate-300 ring-inset hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Save metadata</button>
                <p class="text-xs text-slate-500"><span class="text-rose-600">*</span> Required for approval</p>
            </div>
        </form>

        <aside class="space-y-5 border-t border-slate-200 bg-slate-50/60 px-5 py-5 lg:border-t-0 lg:border-l">
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-slate-900">Review</h3>
                {{-- Events are recorded under the signed-in account (no typed reviewer name). --}}
                <p class="text-sm text-slate-600">Acting as <strong class="font-medium text-slate-900">{{ auth()->user()->name }}</strong> <span class="text-slate-500">({{ auth()->user()->roleLabel() }})</span></p>
                <p id="cur-permission" class="rounded-md bg-slate-100 px-3 py-2 text-xs text-slate-700" hidden></p>
                <div id="cur-note-wrap">
                    <label for="cur-note" class="{{ $label }}">Note <span id="cur-note-hint" class="font-normal text-slate-500">(required to reject)</span></label>
                    <textarea id="cur-note" rows="3" maxlength="2000" class="{{ $input }} bg-white"></textarea>
                    <p data-error-for="note" class="{{ $error }}" hidden></p>
                </div>

                <div id="curation-action-error" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" hidden></div>

                <div class="flex flex-wrap gap-2">
                    <button id="curation-approve" type="button" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">Approve</button>
                    <button id="curation-reject" type="button" class="rounded-md bg-white px-4 py-2 text-sm font-medium text-rose-700 ring-1 ring-rose-200 ring-inset hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50">Reject</button>
                    <button id="curation-reopen" type="button" class="rounded-md bg-white px-4 py-2 text-sm font-medium text-slate-800 ring-1 ring-slate-300 ring-inset hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" hidden>Reopen for review</button>
                </div>
                <p id="cur-approve-hint" class="text-xs text-slate-500">Approve saves the metadata above first.</p>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-slate-900">History</h3>
                <ol id="curation-history" class="mt-3 space-y-3 text-sm"></ol>
                <p id="curation-history-empty" class="mt-3 text-sm text-slate-500" hidden>No curation activity yet.</p>
                <p id="curation-history-error" class="mt-3 text-sm text-rose-700" hidden>History could not be loaded.</p>
            </div>
        </aside>
    </div>
</section>
