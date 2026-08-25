<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

function createAbogado(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'current_session_id' => null,
    ], $overrides));
    $user->assignRole('abogado');

    return $user;
}

function giveActiveSubscription(User $user): void
{
    $plan = SubscriptionPlan::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);
}

// ─── Redirect when terms not accepted ─────────────────────

it('redirects abogado with null terms_version_accepted to accept page', function () {
    $user = createAbogado(['terms_version_accepted' => null]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('legal.accept'));
});

it('redirects abogado with old terms_version_accepted to accept page', function () {
    $user = createAbogado(['terms_version_accepted' => '0.9']);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('legal.accept'));
});

// ─── Access granted when terms are current ────────────────

it('allows abogado with current terms_version_accepted to access dashboard', function () {
    $user = createAbogado([
        'terms_version_accepted' => config('legal.terms_version'),
    ]);
    giveActiveSubscription($user);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
});

// ─── Accepting terms ──────────────────────────────────────

it('accept page renders for abogado who has never accepted', function () {
    $user = createAbogado(['terms_version_accepted' => null]);

    $response = $this->actingAs($user)->get(route('legal.accept'));

    $response->assertOk();
    $response->assertSee('Bienvenido a CertiCheck');
});

it('accept page shows update message for abogado with old version', function () {
    $user = createAbogado(['terms_version_accepted' => '0.9']);

    $response = $this->actingAs($user)->get(route('legal.accept'));

    $response->assertOk();
    $response->assertSee('Contenido legal actualizado');
});

it('accepting terms updates user fields and allows access', function () {
    $user = createAbogado([
        'terms_version_accepted' => null,
        'must_change_password' => false,
    ]);

    // Accept terms
    $this->actingAs($user)->post(route('legal.accept.store'))
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    $this->assertNotNull($user->terms_accepted_at);
    $this->assertEquals(config('legal.terms_version'), $user->terms_version_accepted);

    // Login fresh to verify middleware no longer blocks
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this->get(route('error-reports.create'));
    $response->assertOk();
});

// ─── Admin-created lawyer (no public registration checkbox) ──

it('redirects admin-created lawyer to accept terms after password change', function () {
    $lawyer = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);
    $lawyer->assignRole('abogado');

    $response = $this->actingAs($lawyer)->get('/dashboard');

    $response->assertRedirect(route('legal.accept'));
});

it('admin-created lawyer can accept terms and then access dashboard', function () {
    $lawyer = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);
    $lawyer->assignRole('abogado');

    $this->actingAs($lawyer)->post(route('legal.accept.store'))
        ->assertRedirect(route('dashboard'));

    $lawyer->refresh();
    $this->assertEquals(config('legal.terms_version'), $lawyer->terms_version_accepted);

    $this->post('/login', [
        'email' => $lawyer->email,
        'password' => 'password',
    ]);

    $response = $this->get(route('error-reports.create'));
    $response->assertOk();
});

// ─── Admin bypass ─────────────────────────────────────────

it('admin is never redirected by terms middleware regardless of acceptance', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'terms_version_accepted' => null,
    ]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
});

// ─── No redirect loops ────────────────────────────────────

it('does not redirect loop when visiting accept page', function () {
    $user = createAbogado(['terms_version_accepted' => null]);

    $response = $this->actingAs($user)->get(route('legal.accept'));

    $response->assertOk();
    $response->assertDontSee('redirect');
});

it('does not redirect loop when visiting terms page', function () {
    $user = createAbogado(['terms_version_accepted' => null]);

    $response = $this->actingAs($user)->get(route('legal.terms'));

    $response->assertOk();
});

it('does not redirect loop when visiting privacy page', function () {
    $user = createAbogado(['terms_version_accepted' => null]);

    $response = $this->actingAs($user)->get(route('legal.privacy'));

    $response->assertOk();
});
