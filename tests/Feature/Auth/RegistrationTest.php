<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_accepted' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration fails without terms acceptance', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('terms_accepted');
    $response->assertInvalid(['terms_accepted']);
    $this->assertGuest();
});

test('registration stores terms acceptance metadata', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_accepted' => '1',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    $this->assertNotNull($user->terms_accepted_at);
    $this->assertEquals(config('legal.terms_version'), $user->terms_version_accepted);
});

test('verified abogado without subscription is redirected to subscribe page', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'terms_version_accepted' => config('legal.terms_version'),
    ]);
    $user->assignRole('abogado');

    $this->actingAs($user);
    $this->assertAuthenticated();
    $this->assertFalse($user->hasActiveSubscription());

    $response = $this->get('/dashboard');

    $response->assertRedirect(route('subscription.show', absolute: false));
});
