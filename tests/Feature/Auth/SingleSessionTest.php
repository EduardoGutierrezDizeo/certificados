<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

function createActiveSessionFor(User $user): string
{
    $sessionId = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestBrowser',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $user->update(['current_session_id' => $sessionId]);

    return $sessionId;
}

function makeForceToken(User $user, int $minutesFromNow = 3): string
{
    return Crypt::encryptString(json_encode([
        'user_id' => $user->id,
        'expires_at' => now()->addMinutes($minutesFromNow)->timestamp,
    ]));
}

// ─── Login flow ───────────────────────────────────────────

it('allows abogado login without prior active session', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    $this->assertNotNull($user->current_session_id);

    $sessionInDb = DB::table('sessions')
        ->where('id', $user->current_session_id)
        ->first();
    $this->assertNotNull($sessionInDb);
});

it('shows session conflict for abogado with active session', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    createActiveSessionFor($user);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHas('sessionConflict', true);
    $response->assertSessionHas('force_token');
    $response->assertSessionHas('conflict_email', $user->email);
});

it('force login creates new session and updates current_session_id', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $oldSessionId = createActiveSessionFor($user);
    $forceToken = makeForceToken($user);

    $response = $this->post('/login/force', [
        'force_token' => $forceToken,
    ]);

    $response->assertRedirect();

    // Old session row is intentionally preserved — it will be caught by EnsureSingleSession
    $this->assertDatabaseHas('sessions', ['id' => $oldSessionId]);

    $user->refresh();
    $this->assertNotNull($user->current_session_id);
    $this->assertNotEquals($oldSessionId, $user->current_session_id);

    $this->assertDatabaseHas('sessions', ['id' => $user->current_session_id]);
});

it('old session row is preserved after force-login for EnsureSingleSession to invalidate', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $oldSessionId = createActiveSessionFor($user);

    $this->assertDatabaseHas('sessions', ['id' => $oldSessionId]);
    $this->assertEquals($oldSessionId, $user->fresh()->current_session_id);

    $forceToken = makeForceToken($user);

    $this->post('/login/force', [
        'force_token' => $forceToken,
    ]);

    // Old session row still exists in DB
    $this->assertDatabaseHas('sessions', ['id' => $oldSessionId]);

    // But current_session_id no longer points to it
    $this->assertNotEquals($oldSessionId, $user->fresh()->current_session_id);

    $newSessions = DB::table('sessions')
        ->where('user_id', $user->id)
        ->get();

    $this->assertCount(2, $newSessions);
});

it('does not conflict on first-ever login when current_session_id is null', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $this->assertNull($user->current_session_id);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    $this->assertNotNull($user->current_session_id);
    $this->assertDatabaseHas('sessions', ['id' => $user->current_session_id]);
});

// ─── Admin bypass ─────────────────────────────────────────

it('allows admin login even with an active session record', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('admin');

    createActiveSessionFor($user);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.lawyers.index', absolute: false));

    $user->refresh();
    $this->assertNotNull($user->current_session_id);
});

it('admin is never kicked by EnsureSingleSession middleware', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('admin');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $user->refresh();
    $this->assertNotNull($user->current_session_id);

    $user->update(['current_session_id' => Str::random(40)]);

    $response = $this->get('/admin/dashboard');

    $response->assertStatus(200);
});

// ─── Force login token validation ─────────────────────────

it('rejects force login with expired token', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    createActiveSessionFor($user);

    $expiredToken = makeForceToken($user, minutesFromNow: -5);

    $response = $this->post('/login/force', [
        'force_token' => $expiredToken,
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

it('rejects force login with invalid token', function (): void {
    $response = $this->post('/login/force', [
        'force_token' => 'invalid-token-value',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

it('rejects force login for non-abogado user even with valid token', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('admin');

    $token = makeForceToken($user);

    $response = $this->post('/login/force', [
        'force_token' => $token,
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

it('rejects force login when token references a deleted user', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $token = makeForceToken($user);

    $user->delete();

    $response = $this->post('/login/force', [
        'force_token' => $token,
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

// ─── EnsureSingleSession middleware ────────────────────────

it('redirects abogado to login when session no longer matches current_session_id', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $user->update(['current_session_id' => Str::random(40)]);

    $response = $this->get('/dashboard');

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('session_closed_elsewhere', true);
    $this->assertGuest();
});

it('invalidates and regenerates token when session is rejected', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $originalToken = session()->token();

    $user->update(['current_session_id' => Str::random(40)]);

    $this->get('/dashboard');

    $this->assertNotEquals($originalToken, session()->token());
});

// ─── Heartbeat endpoint ───────────────────────────────────

it('heartbeat returns 200 and active true for valid session', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $loginResponse = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $user->refresh();
    $currentSessionId = $user->current_session_id;
    $this->assertNotNull($currentSessionId);

    $sessionCookieName = $this->app['session']->getName();
    $sessionCookie = collect($loginResponse->headers->getCookies())
        ->first(fn ($c) => $c->getName() === $sessionCookieName);
    $this->assertNotNull($sessionCookie);

    $response = $this->call('GET', '/session/heartbeat', [], [
        $sessionCookieName => $sessionCookie->getValue(),
    ]);

    $response->assertOk();
    $response->assertJson(['active' => true]);
});

it('heartbeat returns 401 when session no longer matches current_session_id', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $user->update(['current_session_id' => Str::random(40)]);

    $response = $this->getJson('/session/heartbeat');

    $response->assertStatus(401);
    $response->assertJson(['session_closed' => true]);
});

it('heartbeat redirects unauthenticated requests to login', function (): void {
    $response = $this->getJson('/session/heartbeat');

    $response->assertStatus(302);
    $response->assertRedirect(route('login'));
});

it('heartbeat is not accessible to admin role', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('admin');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this->getJson('/session/heartbeat');

    $response->assertStatus(403);
});

// ─── Logout clears current_session_id ─────────────────────

it('logout clears current_session_id', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $user->refresh();
    $this->assertNotNull($user->current_session_id);

    $this->post('/logout');

    $user->refresh();
    $this->assertNull($user->current_session_id);
});

// ─── Force-login session handoff ──────────────────────────

it('old session is caught by EnsureSingleSession after force-login', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    // User A logs in from browser 1
    $browser1Response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $user->refresh();
    $browser1SessionId = $user->current_session_id;
    $this->assertNotNull($browser1SessionId);

    $sessionCookieName = $this->app['session']->getName();
    $browser1Cookie = collect($browser1Response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === $sessionCookieName);
    $this->assertNotNull($browser1Cookie);

    // User B force-logs in from browser 2
    createActiveSessionFor($user);
    $forceToken = makeForceToken($user);

    $this->post('/login/force', [
        'force_token' => $forceToken,
    ]);

    $user->refresh();
    $newSessionId = $user->current_session_id;
    $this->assertNotEquals($browser1SessionId, $newSessionId);

    // Old session row still exists in DB (not deleted by forceLogin)
    $this->assertDatabaseHas('sessions', ['id' => $browser1SessionId]);

    // Browser 1 makes a heartbeat request with its old cookie
    // Simulate fresh request through kernel (separate PHP process)
    $kernel = $this->app[Kernel::class];
    $request = Request::create(
        '/session/heartbeat',
        'GET',
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_COOKIE' => $sessionCookieName.'='.$browser1Cookie->getValue(),
        ]
    );

    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    // EnsureSingleSession catches the mismatch and returns 401 + session_closed
    $this->assertEquals(401, $response->getStatusCode());
    $body = json_decode($response->getContent(), true);
    $this->assertArrayHasKey('session_closed', $body);
    $this->assertTrue($body['session_closed']);

    // Old session row was NOT deleted — EnsureSingleSession just logged out the guard
    $this->assertDatabaseHas('sessions', ['id' => $browser1SessionId]);
});

it('old session is caught by EnsureSingleSession on any protected route after force-login', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('abogado');

    // Browser 1 logs in
    $browser1Response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $sessionCookieName = $this->app['session']->getName();
    $browser1Cookie = collect($browser1Response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === $sessionCookieName);

    // Browser 2 force-logs in
    createActiveSessionFor($user);
    $this->post('/login/force', [
        'force_token' => makeForceToken($user),
    ]);

    // Browser 1 hits a normal protected route (dashboard), not just heartbeat
    $kernel = $this->app[Kernel::class];
    $request = Request::create(
        '/dashboard',
        'GET',
        [],
        [],
        [],
        [
            'HTTP_COOKIE' => $sessionCookieName.'='.$browser1Cookie->getValue(),
        ]
    );

    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    // Redirect to login with session_closed_elsewhere flash
    $this->assertEquals(302, $response->getStatusCode());
});
