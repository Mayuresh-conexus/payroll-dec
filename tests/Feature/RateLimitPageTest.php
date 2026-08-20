<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('');

        parent::tearDown();
    }

    public function test_a_throttled_restore_says_nothing_was_carried_out(): void
    {
        // Someone who has just tried to restore a database and been turned away
        // needs to know their data is untouched — a bare "429" does not say that.
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = null;

        // The restore route allows three attempts a minute.
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $response = $this->post('/backups/missing_backup.sql.gz/restore', ['confirmation' => 'x']);
        }

        $response->assertStatus(429)
            ->assertSee('Too Many Attempts')
            ->assertSee('Nothing was carried out');
    }
}
