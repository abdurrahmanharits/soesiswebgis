<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL is the public landing page (no login), linking to the catalogue.
     */
    public function test_the_root_is_the_public_landing_page(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Buka katalog')
            ->assertSee('Masuk');
    }
}
