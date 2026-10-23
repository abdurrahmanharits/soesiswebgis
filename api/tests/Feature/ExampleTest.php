<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL sends users to the dataset catalogue.
     */
    public function test_the_application_redirects_to_datasets(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/datasets');
    }
}
