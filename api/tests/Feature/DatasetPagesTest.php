<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatasetPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Internal endpoints require a signed-in account.
        $this->actingAs(User::factory()->role('uploader')->create());

        $this->withoutVite();
    }

    public function test_dataset_index_page_renders(): void
    {
        $this->get('/datasets')
            ->assertOk()
            ->assertSee('data-page="datasets.index"', false)
            ->assertSee('Upload Dataset')
            ->assertSee('Maximum file size: 512 MB');
    }

    public function test_dataset_detail_page_renders_for_uuid(): void
    {
        $id = '01a167f5-760f-7050-9f80-fb2b8a5e0c95';

        $this->get("/datasets/{$id}")
            ->assertOk()
            ->assertSee('data-page="datasets.show"', false)
            ->assertSee("data-dataset-id=\"{$id}\"", false);
    }

    public function test_dataset_detail_page_rejects_non_uuid(): void
    {
        $this->get('/datasets/not-a-uuid')->assertNotFound();
    }
}
