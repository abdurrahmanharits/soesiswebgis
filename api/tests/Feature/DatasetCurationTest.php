<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatasetCurationTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED = [
        'title' => 'Batas Desa Bukit Jaya',
        'description' => 'Batas administrasi desa.',
        'source' => 'Peta desa partisipatif 2025',
        'license' => 'CC-BY-4.0',
    ];

    private User $curator;

    /** Curation now runs as the signed-in account; reviewer_name comes from it. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->curator = User::factory()->role('curator')->create(['name' => 'Reviewer A']);
        $this->actingAs($this->curator);
    }

    private function dataset(array $attributes = []): Dataset
    {
        return Dataset::create([
            'title' => 'Uploaded title',
            'original_filename' => 'desa.zip',
            'format' => 'shapefile',
            'status' => 'ready_for_curation',
            ...$attributes,
        ])->fresh();
    }

    private function url(Dataset|string $dataset, string $action = ''): string
    {
        $id = $dataset instanceof Dataset ? $dataset->id : $dataset;

        return "/api/datasets/{$id}/curation".($action ? "/{$action}" : '');
    }

    public function test_new_datasets_start_pending(): void
    {
        $this->assertSame('pending', $this->dataset()->curation_status);
    }

    public function test_pending_dataset_metadata_can_be_updated(): void
    {
        $dataset = $this->dataset();

        $this->patchJson($this->url($dataset), [
            'description' => 'Batas administrasi desa.',
            'keywords' => ['batas desa', 'administrasi'],
            'topic_category' => 'boundaries',
            'temporal_start' => '2025-01-01',
            'temporal_end' => '2025-12-31',
            'contact_email' => 'gis@example.org',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Batas administrasi desa.')
            ->assertJsonPath('data.keywords', ['batas desa', 'administrasi'])
            ->assertJsonPath('data.temporal_start', '2025-01-01')
            ->assertJsonPath('data.curation_status', 'pending');

        $this->assertDatabaseHas('dataset_curation_events', [
            'dataset_id' => $dataset->id,
            'action' => 'metadata_updated',
            'reviewer_name' => 'Reviewer A',
        ]);
    }

    public function test_saving_unchanged_metadata_records_no_event(): void
    {
        $dataset = $this->dataset();

        $this->patchJson($this->url($dataset), ['title' => 'Uploaded title'])->assertOk();

        $this->assertDatabaseCount('dataset_curation_events', 0);
    }

    public function test_invalid_metadata_returns_422(): void
    {
        $this->patchJson($this->url($this->dataset()), [
            'topic_category' => 'not-a-category',
            'license' => 'other',
            'contact_email' => 'not-an-email',
            'temporal_start' => '2025-06-01',
            'temporal_end' => '2025-01-01',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['topic_category', 'license_other', 'contact_email', 'temporal_end']);
    }

    public function test_events_are_recorded_under_the_signed_in_account(): void
    {
        $dataset = $this->dataset(self::REQUIRED);

        // A typed reviewer name is ignored: identity comes from the session.
        $this->postJson($this->url($dataset, 'approve'), ['reviewer_name' => 'Someone Else'])->assertOk();

        $this->assertDatabaseHas('dataset_curation_events', [
            'dataset_id' => $dataset->id,
            'action' => 'approved',
            'user_id' => $this->curator->id,
            'reviewer_name' => 'Reviewer A',
        ]);
        $this->assertDatabaseMissing('dataset_curation_events', ['reviewer_name' => 'Someone Else']);
    }

    public function test_approve_requires_minimum_metadata(): void
    {
        $dataset = $this->dataset(['title' => 'Only a title']);

        $this->postJson($this->url($dataset, 'approve'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description', 'source', 'license']);

        $this->assertSame('pending', $dataset->fresh()->curation_status);
        $this->assertDatabaseCount('dataset_curation_events', 0);
    }

    public function test_other_license_needs_a_name_before_approval(): void
    {
        $dataset = $this->dataset([...self::REQUIRED, 'license' => 'other']);

        $this->postJson($this->url($dataset, 'approve'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['license_other']);
    }

    public function test_pending_dataset_can_be_approved(): void
    {
        $dataset = $this->dataset(self::REQUIRED);

        $this->postJson($this->url($dataset, 'approve'), ['note' => 'Metadata dan preview valid.'])
            ->assertOk()
            ->assertJsonPath('data.curation_status', 'approved')
            ->assertJsonPath('data.status', 'ready_for_curation');

        $this->assertDatabaseHas('dataset_curation_events', [
            'dataset_id' => $dataset->id,
            'action' => 'approved',
            'reviewer_name' => 'Reviewer A',
            'note' => 'Metadata dan preview valid.',
            'from_status' => 'pending',
            'to_status' => 'approved',
        ]);
    }

    public function test_pending_dataset_can_be_rejected_with_reason(): void
    {
        $dataset = $this->dataset();

        $this->postJson($this->url($dataset, 'reject'), ['note' => 'Sumber tidak jelas.'])
            ->assertOk()
            ->assertJsonPath('data.curation_status', 'rejected');

        $this->assertDatabaseHas('dataset_curation_events', [
            'action' => 'rejected',
            'note' => 'Sumber tidak jelas.',
            'from_status' => 'pending',
            'to_status' => 'rejected',
        ]);
    }

    public function test_reject_requires_a_reason(): void
    {
        $this->postJson($this->url($this->dataset(), 'reject'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }

    public function test_rejection_keeps_dataset_and_files(): void
    {
        $dataset = $this->dataset(['postgis_schema' => 'ingest', 'postgis_table' => 'ds_x', 'processed_path' => 'processed/x/cog.tif']);

        $this->postJson($this->url($dataset, 'reject'), ['note' => 'No'])->assertOk();

        $this->assertDatabaseHas('datasets', [
            'id' => $dataset->id,
            'postgis_table' => 'ds_x',
            'processed_path' => 'processed/x/cog.tif',
        ]);
    }

    public function test_approved_and_rejected_datasets_can_be_reopened(): void
    {
        foreach (['approved', 'rejected'] as $status) {
            $dataset = $this->dataset(['curation_status' => $status]);

            $this->postJson($this->url($dataset, 'reopen'), ['note' => 'Revisi'])
                ->assertOk()
                ->assertJsonPath('data.curation_status', 'pending');

            $this->assertDatabaseHas('dataset_curation_events', [
                'dataset_id' => $dataset->id,
                'action' => 'reopened',
                'from_status' => $status,
                'to_status' => 'pending',
            ]);
        }
    }

    public function test_invalid_transitions_return_409(): void
    {
        $approved = $this->dataset([...self::REQUIRED, 'curation_status' => 'approved']);
        $pending = $this->dataset();

        $this->postJson($this->url($approved, 'approve'), [])->assertStatus(409);
        $this->postJson($this->url($approved, 'reject'), ['note' => 'x'])->assertStatus(409);
        $this->postJson($this->url($pending, 'reopen'), [])->assertStatus(409);
    }

    public function test_approved_metadata_cannot_be_edited_without_reopening(): void
    {
        $dataset = $this->dataset([...self::REQUIRED, 'curation_status' => 'approved']);

        $this->patchJson($this->url($dataset), ['description' => 'Changed'])
            ->assertStatus(409)
            ->assertJson(['message' => 'Reopen this dataset before editing its metadata.']);

        $this->assertSame(self::REQUIRED['description'], $dataset->fresh()->description);
    }

    public function test_failed_dataset_cannot_be_curated(): void
    {
        $dataset = $this->dataset([...self::REQUIRED, 'status' => 'failed']);

        $this->postJson($this->url($dataset, 'approve'), [])
            ->assertStatus(409)
            ->assertJson(['message' => 'Curation unavailable because dataset processing failed.']);

        $this->patchJson($this->url($dataset), ['description' => 'x'])->assertStatus(409);
    }

    public function test_processing_dataset_cannot_be_curated(): void
    {
        foreach (['queued', 'processing'] as $status) {
            $this->postJson($this->url($this->dataset([...self::REQUIRED, 'status' => $status]), 'approve'), [])
                ->assertStatus(409)
                ->assertJson(['message' => 'Dataset must finish processing before it can be curated.']);
        }
    }

    public function test_history_lists_events_newest_first(): void
    {
        $dataset = $this->dataset(self::REQUIRED);

        $this->postJson($this->url($dataset, 'approve'), [])->assertOk();
        $this->actingAs(User::factory()->role('admin')->create(['name' => 'B']))
            ->postJson($this->url($dataset, 'reopen'), ['note' => 'Revisi'])->assertOk();

        $this->getJson($this->url($dataset, 'history'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'reopened')
            ->assertJsonPath('data.0.reviewer_name', 'B')
            ->assertJsonPath('data.1.action', 'approved');
    }

    public function test_unknown_dataset_returns_404(): void
    {
        $this->getJson($this->url((string) Str::uuid(), 'history'))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Dataset not found.']);
    }

    public function test_preview_still_works_after_approval(): void
    {
        $dataset = $this->dataset([...self::REQUIRED, 'format' => 'geotiff', 'processed_path' => 'processed/none/cog.tif']);

        $this->postJson($this->url($dataset, 'approve'), [])->assertOk();

        // Passes the ready check (would be 409 otherwise); 404 only because no file exists in tests.
        $this->getJson("/api/datasets/{$dataset->id}/preview/raster")
            ->assertNotFound()
            ->assertJson(['message' => 'Processed raster file was not found.']);
    }
}
