<?php

namespace Tests\Feature;

use App\Jobs\ProcessSpatialDataset;
use App\Models\Dataset;
use App\Models\DatasetCurationEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED = [
        'title' => 'Batas Desa',
        'description' => 'Batas administrasi desa.',
        'source' => 'Survei lapangan',
        'license' => 'CC-BY-4.0',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function as(string $role, array $attributes = []): User
    {
        $user = User::factory()->role($role)->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    private function dataset(array $attributes = []): Dataset
    {
        return Dataset::create([
            'original_filename' => 'desa.zip',
            'format' => 'shapefile',
            'status' => 'ready_for_curation',
            ...self::REQUIRED,
            ...$attributes,
        ]);
    }

    private function curation(Dataset $dataset, string $action = ''): string
    {
        return "/api/datasets/{$dataset->id}/curation".($action ? "/{$action}" : '');
    }

    // ---- Every role sees the internal area ---------------------------------

    public function test_every_role_can_use_internal_pages_and_read_endpoints(): void
    {
        $dataset = $this->dataset();

        foreach (User::ROLES as $role) {
            $this->as($role);

            $this->get('/datasets')->assertOk();
            $this->get("/datasets/{$dataset->id}")->assertOk();
            $this->get('/map')->assertOk();
            $this->getJson('/api/datasets')->assertOk();
            $this->getJson("/api/datasets/{$dataset->id}")->assertOk();
            $this->getJson($this->curation($dataset, 'history'))->assertOk();
            $this->getJson('/api/map/layers')->assertOk();
        }
    }

    // ---- Upload & ownership -------------------------------------------------

    public function test_upload_records_uploader_and_organization(): void
    {
        Storage::fake('local');
        Queue::fake();
        $organization = Organization::create(['name' => 'WWF-Indonesia']);
        $user = $this->as('uploader', ['organization_id' => $organization->id, 'name' => 'Rina']);

        $id = $this->post('/api/datasets', ['title' => 'Desa', 'file' => UploadedFile::fake()->create('desa.zip', 10)], ['Accept' => 'application/json'])
            ->assertStatus(202)
            ->assertJsonPath('data.uploaded_by', $user->id)
            ->assertJsonPath('data.organization_id', $organization->id)
            ->json('data.id');

        Queue::assertPushedOn('spatial', ProcessSpatialDataset::class);

        $this->getJson("/api/datasets/{$id}")
            ->assertJsonPath('data.uploader.name', 'Rina')
            ->assertJsonPath('data.organization.name', 'WWF-Indonesia');
    }

    // ---- Uploader -----------------------------------------------------------

    public function test_uploader_can_edit_metadata_of_own_upload_only(): void
    {
        $uploader = $this->as('uploader', ['name' => 'Rina']);
        $own = $this->dataset(['uploaded_by' => $uploader->id]);
        $other = $this->dataset(['uploaded_by' => User::factory()->create()->id]);
        $legacy = $this->dataset();

        $this->patchJson($this->curation($own), ['description' => 'Dilengkapi uploader.'])->assertOk();
        $this->assertDatabaseHas('dataset_curation_events', ['dataset_id' => $own->id, 'user_id' => $uploader->id, 'reviewer_name' => 'Rina']);

        foreach ([$other, $legacy] as $dataset) {
            $this->patchJson($this->curation($dataset), ['description' => 'x'])
                ->assertForbidden()
                ->assertJson(['message' => 'You can only edit the metadata of datasets you uploaded.']);
        }
    }

    public function test_uploader_cannot_decide_on_datasets(): void
    {
        $uploader = $this->as('uploader');
        $dataset = $this->dataset(['uploaded_by' => $uploader->id]);
        $approved = $this->dataset(['curation_status' => 'approved']);

        $this->postJson($this->curation($dataset, 'approve'))->assertForbidden()->assertJsonPath('message', 'Forbidden.');
        $this->postJson($this->curation($dataset, 'reject'), ['note' => 'x'])->assertForbidden();
        $this->postJson($this->curation($approved, 'reopen'))->assertForbidden();

        $this->assertSame('pending', $dataset->fresh()->curation_status);
    }

    public function test_uploader_and_curator_cannot_manage_users(): void
    {
        foreach (['uploader', 'curator'] as $role) {
            $this->as($role);

            $this->get('/admin/users')->assertForbidden();
            $this->post('/admin/users', [])->assertForbidden();
            $this->post('/admin/organizations', ['name' => 'X'])->assertForbidden();
        }
    }

    // ---- Curator / admin ------------------------------------------------------

    public function test_curator_can_approve_reject_and_reopen(): void
    {
        $curator = $this->as('curator', ['name' => 'Budi']);
        $a = $this->dataset();
        $b = $this->dataset();

        $this->postJson($this->curation($a, 'approve'))->assertOk();
        $this->postJson($this->curation($a, 'reopen'))->assertOk();
        $this->postJson($this->curation($b, 'reject'), ['note' => 'Sumber tidak jelas.'])->assertOk();

        $this->assertSame(3, DatasetCurationEvent::where('user_id', $curator->id)->where('reviewer_name', 'Budi')->count());
    }

    public function test_nobody_approves_their_own_upload(): void
    {
        foreach (['curator', 'admin'] as $role) {
            $user = $this->as($role);
            $dataset = $this->dataset(['uploaded_by' => $user->id]);

            $this->postJson($this->curation($dataset, 'approve'))
                ->assertForbidden()
                ->assertJson(['message' => 'You cannot approve a dataset you uploaded.']);

            // Rejecting (or editing) the own upload stays allowed.
            $this->postJson($this->curation($dataset, 'reject'), ['note' => 'Perlu revisi.'])->assertOk();
        }
    }

    public function test_another_curator_can_approve_the_upload(): void
    {
        $uploader = User::factory()->role('curator')->create();
        $dataset = $this->dataset(['uploaded_by' => $uploader->id]);

        $this->as('curator');

        $this->postJson($this->curation($dataset, 'approve'))->assertOk();
    }

    public function test_legacy_history_without_account_still_lists(): void
    {
        $dataset = $this->dataset();
        $dataset->curationEvents()->create([
            'action' => 'approved', 'reviewer_name' => 'QA', 'from_status' => 'pending', 'to_status' => 'approved',
        ]);
        $this->as('uploader');

        $this->getJson($this->curation($dataset, 'history'))
            ->assertOk()
            ->assertJsonPath('data.0.reviewer_name', 'QA')
            ->assertJsonPath('data.0.user_id', null);
    }

    public function test_curation_panel_shows_account_instead_of_reviewer_field(): void
    {
        $this->as('curator', ['name' => 'Budi Santoso']);

        $this->get("/datasets/{$this->dataset()->id}")
            ->assertSee('Acting as')
            ->assertSee('Budi Santoso')
            ->assertDontSee('id="cur-reviewer"', false);
    }
}
