<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Editorial review of ingested datasets. Only touches curation columns and the
 * dataset_curation_events audit table; ingestion status and files are never modified.
 *
 *   pending  -> approved | rejected
 *   approved -> pending (reopen)
 *   rejected -> pending (reopen)
 */
class DatasetCurationController extends Controller
{
    /** ISO 19115 MD_TopicCategoryCode. */
    public const TOPIC_CATEGORIES = [
        'farming', 'biota', 'boundaries', 'climatologyMeteorologyAtmosphere', 'economy',
        'elevation', 'environment', 'geoscientificInformation', 'health', 'imageryBaseMapsEarthCover',
        'intelligenceMilitary', 'inlandWaters', 'location', 'oceans', 'planningCadastre',
        'society', 'structure', 'transportation', 'utilitiesCommunication',
    ];

    public const LICENSES = ['CC-BY-4.0', 'CC-BY-SA-4.0', 'CC0-1.0', 'restricted', 'other'];

    private const REQUIRED_FOR_APPROVAL = ['title', 'description', 'source', 'license'];

    /**
     * Save descriptive metadata. Only allowed while pending; approved/rejected
     * datasets must be reopened first so every change is re-reviewed.
     */
    public function update(Request $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_name' => ['required', 'string', 'max:100'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'keywords' => ['sometimes', 'nullable', 'array', 'max:30'],
            'keywords.*' => ['string', 'max:60', 'distinct'],
            'topic_category' => ['sometimes', 'nullable', Rule::in(self::TOPIC_CATEGORIES)],
            'source' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'source_organization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'license' => ['sometimes', 'nullable', Rule::in(self::LICENSES)],
            // No `sometimes`: choosing "other" without naming the license must fail even if the key is absent.
            'license_other' => ['nullable', 'string', 'max:255', 'required_if:license,other'],
            'usage_constraints' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lineage' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'temporal_start' => ['sometimes', 'nullable', 'date'],
            'temporal_end' => ['sometimes', 'nullable', 'date', 'after_or_equal:temporal_start'],
            'geographic_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $reviewer = $validated['reviewer_name'];
        unset($validated['reviewer_name']);

        if (($validated['license'] ?? $dataset->license) !== 'other') {
            $validated['license_other'] = null;
        }

        $dataset = DB::transaction(function () use ($dataset, $validated, $reviewer) {
            $locked = $this->lockCuratable($dataset);

            if ($locked->curation_status !== 'pending') {
                $this->fail(409, 'Reopen this dataset before editing its metadata.');
            }

            $locked->fill($validated);
            $changed = array_keys($locked->getDirty());

            if ($changed !== []) {
                $locked->save();
                $locked->curationEvents()->create([
                    'action' => 'metadata_updated',
                    'reviewer_name' => $reviewer,
                    'from_status' => 'pending',
                    'to_status' => 'pending',
                    'changes' => $changed,
                ]);
            }

            return $locked;
        });

        return response()->json([
            'message' => 'Metadata saved.',
            'data' => $dataset->fresh(),
        ]);
    }

    public function approve(Request $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_name' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->transition($dataset, ['pending'], 'approved', 'approved', $validated, function (Dataset $locked) {
            $missing = array_filter(self::REQUIRED_FOR_APPROVAL, fn ($field) => blank($locked->{$field}));

            if ($locked->license === 'other' && blank($locked->license_other)) {
                $missing[] = 'license_other';
            }

            if ($missing !== []) {
                throw ValidationException::withMessages(
                    collect($missing)->mapWithKeys(fn ($field) => [$field => 'Required before approval.'])->all(),
                );
            }
        });
    }

    public function reject(Request $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_name' => ['required', 'string', 'max:100'],
            'note' => ['required', 'string', 'max:2000'],
        ], [
            'note.required' => 'A reason is required to reject a dataset.',
        ]);

        return $this->transition($dataset, ['pending'], 'rejected', 'rejected', $validated);
    }

    public function reopen(Request $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_name' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->transition($dataset, ['approved', 'rejected'], 'pending', 'reopened', $validated);
    }

    public function history(Dataset $dataset): JsonResponse
    {
        return response()->json([
            'data' => $dataset->curationEvents()
                ->get(['id', 'action', 'reviewer_name', 'note', 'from_status', 'to_status', 'changes', 'created_at']),
        ]);
    }

    /**
     * Applies a status change under a row lock, so concurrent requests (e.g. two
     * tabs) see the current state and an outdated transition gets 409.
     */
    private function transition(Dataset $dataset, array $from, string $to, string $action, array $input, ?callable $guard = null): JsonResponse
    {
        $dataset = DB::transaction(function () use ($dataset, $from, $to, $action, $input, $guard) {
            $locked = $this->lockCuratable($dataset);
            $current = $locked->curation_status;

            if (! in_array($current, $from, true)) {
                $this->fail(409, "This action is not allowed while the dataset is {$current}.");
            }

            $guard?->__invoke($locked);

            $locked->update(['curation_status' => $to]);
            $locked->curationEvents()->create([
                'action' => $action,
                'reviewer_name' => $input['reviewer_name'],
                'note' => $input['note'] ?? null,
                'from_status' => $current,
                'to_status' => $to,
            ]);

            return $locked;
        });

        return response()->json([
            'message' => match ($action) {
                'approved' => 'Dataset approved.',
                'rejected' => 'Dataset rejected.',
                default => 'Dataset reopened for review.',
            },
            'data' => $dataset->fresh(),
        ]);
    }

    private function lockCuratable(Dataset $dataset): Dataset
    {
        $locked = Dataset::query()->whereKey($dataset->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->status === 'failed') {
            $this->fail(409, 'Curation unavailable because dataset processing failed.');
        }

        if ($locked->status !== 'ready_for_curation') {
            $this->fail(409, 'Dataset must finish processing before it can be curated.');
        }

        return $locked;
    }

    private function fail(int $status, string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
