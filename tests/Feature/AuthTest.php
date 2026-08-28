<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_email_and_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'username' => 'admintest',
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $response = $this->post('/login', [
            'login' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_username(): void
    {
        User::factory()->create([
            'email' => 'admin2@test.com',
            'username' => 'admintwo',
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $response = $this->post('/login', [
            'login' => 'admintwo',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'admin3@test.com',
            'username' => 'adminthree',
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $response = $this->post('/login', [
            'login' => 'admin3@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_deactivated_account_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'off@test.com',
            'username' => 'offuser',
            'password' => 'password123',
            'role' => 'pj',
            'status' => 'nonaktif',
        ]);

        $response = $this->post('/login', [
            'login' => 'off@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_idle_session_past_lifetime_is_forced_to_login_again(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $staleTimestamp = now()->subMinutes(config('session.lifetime') + 1)->timestamp;

        $response = $this->actingAs($user)
            ->withSession(['last_active_at' => $staleTimestamp])
            ->get('/');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_session_within_lifetime_stays_logged_in(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $recentTimestamp = now()->subMinute()->timestamp;

        $response = $this->actingAs($user)
            ->withSession(['last_active_at' => $recentTimestamp])
            ->get('/');

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_is_not_cached_by_the_browser(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_stale_csrf_token_redirects_back_to_login_gracefully_instead_of_a_raw_error_page(): void
    {
        $request = \Illuminate\Http\Request::create('/login', 'POST');

        $response = $this->app->make(ExceptionHandler::class)
            ->render($request, new HttpException(419, 'CSRF token mismatch.'));

        $response = \Illuminate\Testing\TestResponse::fromBaseResponse($response);

        $response->assertRedirect(route('login'));
    }
}
