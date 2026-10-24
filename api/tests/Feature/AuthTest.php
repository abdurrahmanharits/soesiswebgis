<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create([
            'username' => 'rina.pratiwi',
            'email' => 'rina@example.org',
            'password' => 'correct-horse-battery',
            ...$attributes,
        ]);
    }

    // ---- Guests -----------------------------------------------------------

    public function test_guests_are_sent_to_login_from_internal_pages(): void
    {
        $id = (string) Str::uuid();

        foreach (['/datasets', "/datasets/{$id}", '/map', '/admin/users'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_guests_get_401_json_from_internal_api(): void
    {
        $id = (string) Str::uuid();

        $requests = [
            ['GET', '/api/datasets'],
            ['POST', '/api/datasets'],
            ['GET', "/api/datasets/{$id}"],
            ['GET', "/api/datasets/{$id}/preview/vector"],
            ['GET', "/api/datasets/{$id}/preview/raster"],
            ['GET', "/api/datasets/{$id}/preview/raster/info"],
            ['PATCH', "/api/datasets/{$id}/curation"],
            ['POST', "/api/datasets/{$id}/curation/approve"],
            ['POST', "/api/datasets/{$id}/curation/reject"],
            ['POST', "/api/datasets/{$id}/curation/reopen"],
            ['GET', "/api/datasets/{$id}/curation/history"],
            ['GET', '/api/map/layers'],
        ];

        foreach ($requests as [$method, $url]) {
            $this->json($method, $url)
                ->assertStatus(401)
                ->assertExactJson(['message' => 'Unauthenticated.']);
        }
    }

    public function test_public_area_stays_open_for_guests(): void
    {
        $dataset = Dataset::create([
            'title' => 'Published', 'original_filename' => 'a.zip', 'format' => 'shapefile',
            'status' => 'ready_for_curation', 'curation_status' => 'approved',
        ]);

        foreach (['/', '/catalogue', "/catalogue/{$dataset->id}", '/catalogue/map', '/login'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->getJson('/api/catalogue')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/catalogue/{$dataset->id}")->assertOk();
        // Reaches the controller (no auth wall): 404 because the dataset has no PostGIS table.
        $this->getJson("/api/catalogue/{$dataset->id}/preview/vector")->assertNotFound();
    }

    // ---- Login ------------------------------------------------------------

    public function test_login_with_email_or_username(): void
    {
        $user = $this->user();

        foreach (['rina@example.org', 'RINA.PRATIWI', 'Rina@Example.org'] as $login) {
            $this->post('/login', ['login' => $login, 'password' => 'correct-horse-battery'])
                ->assertRedirect('/datasets');
            $this->assertAuthenticatedAs($user);

            $this->post('/logout');
        }
    }

    public function test_login_redirects_to_intended_page(): void
    {
        $this->user();

        $this->get('/map')->assertRedirect('/login');

        $this->post('/login', ['login' => 'rina.pratiwi', 'password' => 'correct-horse-battery'])
            ->assertRedirect('/map');
    }

    public function test_login_regenerates_the_session(): void
    {
        $this->user();
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['login' => 'rina.pratiwi', 'password' => 'correct-horse-battery']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_invalid_credentials_use_one_generic_message(): void
    {
        $this->user();
        $this->user(['username' => 'inactive', 'email' => 'inactive@example.org', 'is_active' => false]);

        $attempts = [
            ['login' => 'rina.pratiwi', 'password' => 'wrong-password'],
            ['login' => 'nobody', 'password' => 'correct-horse-battery'],
            ['login' => 'inactive', 'password' => 'correct-horse-battery'],
        ];

        foreach ($attempts as $credentials) {
            $this->from('/login')->post('/login', $credentials)
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['login' => 'The provided credentials are invalid.']);
            $this->assertGuest();
        }
    }

    public function test_repeated_failures_are_throttled(): void
    {
        $this->user();
        RateLimiter::clear('login:'.sha1('rina.pratiwi|127.0.0.1'));

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'rina.pratiwi', 'password' => 'wrong']);
        }

        $this->post('/login', ['login' => 'rina.pratiwi', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('login');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_signed_in_users_skip_the_login_page(): void
    {
        $this->actingAs($this->user())->get('/login')->assertRedirect('/datasets');
    }

    // ---- Logout / deactivation ---------------------------------------------

    public function test_logout_ends_access(): void
    {
        $this->actingAs($this->user());

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/datasets')->assertRedirect('/login');
        $this->getJson('/api/datasets')->assertStatus(401);
    }

    public function test_deactivated_user_loses_access_on_next_request(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->getJson('/api/datasets')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertGuest();
    }

    // ---- Header -----------------------------------------------------------

    public function test_internal_header_shows_account_and_logout(): void
    {
        $this->actingAs($this->user(['name' => 'Rina Pratiwi', 'role' => 'curator']))
            ->get('/datasets')
            ->assertSee('Rina Pratiwi')
            ->assertSee('Curator')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('name="csrf-token"', false)
            ->assertDontSee('>Users<', false);
    }

    // ---- CSRF (Laravel skips this middleware in tests unless forced) -------

    private function enforceCsrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_internal_write_requests_require_a_csrf_token(): void
    {
        $this->enforceCsrf();
        $this->actingAs(User::factory()->role('curator')->create());
        $dataset = Dataset::create(['title' => 'X', 'original_filename' => 'x.zip', 'format' => 'shapefile', 'status' => 'ready_for_curation']);

        $this->patchJson("/api/datasets/{$dataset->id}/curation", ['description' => 'x'])
            ->assertStatus(419);

        // A cross-site request (e.g. a form on another website) is rejected too.
        $this->withHeader('Sec-Fetch-Site', 'cross-site')
            ->patchJson("/api/datasets/{$dataset->id}/curation", ['description' => 'x'])
            ->assertStatus(419);

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->patchJson("/api/datasets/{$dataset->id}/curation", ['description' => 'x'])
            ->assertOk();
    }

    public function test_login_form_requires_a_csrf_token(): void
    {
        $this->enforceCsrf();
        $this->user();

        $this->post('/login', ['login' => 'rina.pratiwi', 'password' => 'correct-horse-battery'])->assertStatus(419);
        $this->assertGuest();
    }
}
