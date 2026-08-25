<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\EpaycoSignatureService;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

it('shows active subscription plans on the subscribe page', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $activePlan = SubscriptionPlan::factory()->create(['name' => 'Premium', 'is_active' => true]);
    $inactivePlan = SubscriptionPlan::factory()->create(['name' => 'Legacy', 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('subscription.show'))
        ->assertOk()
        ->assertSee('Premium')
        ->assertDontSee('Legacy');
});

it('shows empty state when no active plans exist', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    SubscriptionPlan::factory()->create(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('subscription.show'))
        ->assertOk()
        ->assertSee('No hay planes disponibles');
});

it('checkout creates payment with correct plan price', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Premium',
        'price_in_cents' => 7500000,
    ]);

    $this->actingAs($user)
        ->get(route('subscription.checkout', $plan))
        ->assertOk();

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'amount_in_cents' => 7500000,
        'status' => 'pending',
    ]);
});

it('checkout shows correct plan name and amount in view', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Premium',
        'price_in_cents' => 7500000,
    ]);

    $this->actingAs($user)
        ->get(route('subscription.checkout', $plan))
        ->assertOk()
        ->assertSee('Premium')
        ->assertSee('75000');
});

it('webhook creates new subscription with plan duration_months', function (): void {
    $this->mock(EpaycoSignatureService::class, fn ($mock) => $mock
        ->shouldReceive('verifyConfirmationSignature')->once()->andReturn(true));

    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Premium',
        'duration_months' => 3,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'reference' => 'TEST-REF-001',
        'status' => 'pending',
    ]);

    $payload = [
        'x_id_factura' => 'TEST-REF-001',
        'x_transaction_state' => 'Aceptada',
        'x_ref_payco' => '12345',
        'x_transaction_id' => '67890',
        'x_amount' => '75000',
        'x_currency_code' => 'COP',
        'x_signature' => 'test-signature',
    ];

    $this->postJson(route('webhooks.epayco'), $payload)
        ->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe('approved');

    $subscription = Subscription::where('user_id', $user->id)->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->subscription_plan_id)->toBe($plan->id);
    expect($subscription->plan)->toBe('Premium');
    expect($subscription->starts_at->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
    expect($subscription->ends_at->format('Y-m-d'))->toBe(now()->addMonths(3)->format('Y-m-d'));
});

it('webhook extends existing subscription by plan duration_months', function (): void {
    $this->mock(EpaycoSignatureService::class, fn ($mock) => $mock
        ->shouldReceive('verifyConfirmationSignature')->once()->andReturn(true));

    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $oldPlan = SubscriptionPlan::factory()->create(['name' => 'Basic', 'duration_months' => 1]);
    $newPlan = SubscriptionPlan::factory()->create(['name' => 'Premium', 'duration_months' => 3]);

    $existingEnd = now()->addDays(15);
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'subscription_plan_id' => $oldPlan->id,
        'plan' => 'Basic',
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => $existingEnd,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $newPlan->id,
        'reference' => 'TEST-REF-002',
        'status' => 'pending',
    ]);

    $payload = [
        'x_id_factura' => 'TEST-REF-002',
        'x_transaction_state' => 'Aceptada',
        'x_ref_payco' => '12345',
        'x_transaction_id' => '67890',
        'x_amount' => '75000',
        'x_currency_code' => 'COP',
        'x_signature' => 'test-signature',
    ];

    $this->postJson(route('webhooks.epayco'), $payload)
        ->assertOk();

    $subscription->refresh();
    expect($subscription->plan)->toBe('Premium');
    expect($subscription->subscription_plan_id)->toBe($newPlan->id);
    expect($subscription->ends_at->format('Y-m-d'))->toBe($existingEnd->addMonths(3)->format('Y-m-d'));
});

it('webhook rejects payment with invalid signature', function (): void {
    $payload = [
        'x_id_factura' => 'TEST-REF-003',
        'x_transaction_state' => 'Aceptada',
        'x_signature' => 'invalid_signature',
    ];

    $this->postJson(route('webhooks.epayco'), $payload)
        ->assertStatus(401);
});

it('return page loads without errors for successful payment', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->get(route('subscription.return'))
        ->assertOk()
        ->assertSee('Confirmando tu pago')
        ->assertSee('Estamos confirmando tu pago');
});

it('return page loads without errors for failed payment', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->get(route('subscription.return', ['x_response' => 'Rechazada']))
        ->assertOk()
        ->assertSee('El pago no se completó')
        ->assertSee(route('subscription.show'))
        ->assertDontSee(route('subscription.checkout', 1));
});

it('return page for timed out payment links to plan selection', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->get(route('subscription.return'))
        ->assertOk()
        ->assertSee(route('subscription.show'));
});

it('return page retry link points to plan selection not checkout', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $response = $this->actingAs($user)
        ->get(route('subscription.return', ['x_response' => 'Fallida']));

    $response->assertOk();

    $content = $response->content();
    preg_match_all("/route\('subscription\.checkout'[^)]*\)/", $content, $matches);
    expect($matches[0])->toHaveCount(0);
});

it('unauthenticated user cannot access subscribe page', function (): void {
    $this->get(route('subscription.show'))
        ->assertRedirect('/login');
});
