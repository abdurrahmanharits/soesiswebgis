<?php

namespace Tests\Feature;

use App\Models\Dataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CataloguePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function dataset(array $attributes = []): Dataset
    {
        return Dataset::create([
            'title' => 'Batas Desa Bukit Jaya',
            'original_filename' => 'desa.zip',
            'format' => 'shapefile',
            'status' => 'ready_for_curation',
            'curation_status' => 'approved',
            'crs' => 'EPSG:4326',
            'srid' => 4326,
            'bbox' => [96.0, 4.0, 96.1, 4.1],
            'description' => 'Batas administrasi desa.',
            'source' => 'Survei lapangan',
            'source_organization' => 'WWF-Indonesia',
            'license' => 'CC-BY-4.0',
            ...$attributes,
        ]);
    }

    public function test_catalogue_page_renders_in_indonesian(): void
    {
        $this->get('/catalogue')
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('data-page="catalogue.index"', false)
            ->assertSee('Katalog Dataset')
            ->assertDontSee('Upload Dataset');
    }

    public function test_public_detail_shows_metadata_and_attribution(): void
    {
        $dataset = $this->dataset(['contact_name' => 'Rina', 'contact_email' => 'rina@example.org', 'postgis_table' => 'ds_internal']);
        $dataset->curationEvents()->forceCreate([
            'action' => 'approved', 'reviewer_name' => 'Secret Reviewer', 'note' => 'internal note',
            'from_status' => 'pending', 'to_status' => 'approved', 'created_at' => '2025-06-01 09:00:00',
        ]);

        $this->get("/catalogue/{$dataset->id}")
            ->assertOk()
            ->assertSee('data-page="catalogue.show"', false)
            ->assertSee('Batas Desa Bukit Jaya')
            ->assertSee('Survei lapangan')
            ->assertSee('CC BY 4.0')
            ->assertSee('WWF-Indonesia (2025). Batas Desa Bukit Jaya. CC BY 4.0.')
            ->assertSee('Rina')
            ->assertSee("/catalogue/map?layers={$dataset->id}", false)
            ->assertDontSee('rina@example.org')
            ->assertDontSee('Secret Reviewer')
            ->assertDontSee('internal note')
            ->assertDontSee('ds_internal');
    }

    public function test_attribution_skips_missing_parts(): void
    {
        $dataset = $this->dataset(['source_organization' => null, 'license' => 'other', 'license_other' => 'Izin khusus']);

        $this->get("/catalogue/{$dataset->id}")->assertSee('Batas Desa Bukit Jaya. Izin khusus.');
    }

    public function test_restricted_license_is_labelled(): void
    {
        $dataset = $this->dataset(['license' => 'restricted']);

        $this->get("/catalogue/{$dataset->id}")
            ->assertSee('Terbatas (restricted)')
            ->assertSee('Penggunaan dataset ini dibatasi.');
    }

    public function test_unpublished_detail_is_a_404_page(): void
    {
        foreach (['pending', 'rejected'] as $status) {
            $dataset = $this->dataset(['curation_status' => $status]);

            $this->get("/catalogue/{$dataset->id}")
                ->assertNotFound()
                ->assertSee('Dataset tidak tersedia')
                ->assertDontSee('Batas administrasi desa.');
        }

        $failed = $this->dataset(['status' => 'failed', 'curation_status' => 'pending']);
        $this->get("/catalogue/{$failed->id}")->assertNotFound();
        $this->get('/catalogue/'.Str::uuid())->assertNotFound();
        $this->get('/catalogue/not-a-uuid')->assertNotFound();
    }

    public function test_public_map_page_uses_catalogue_source(): void
    {
        $this->get('/catalogue/map')
            ->assertOk()
            ->assertSee('data-page="catalogue.map"', false)
            ->assertSee('Tambah dataset')
            ->assertDontSee('Go to Datasets');
    }

    public function test_internal_map_page_is_unchanged(): void
    {
        $this->get('/map')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('data-page="map.index"', false)
            ->assertSee('Add datasets')
            ->assertSee('Go to Datasets');
    }
}
