<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\PaymentConfirmed;
use App\Services\EpaycoSignatureService;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

function epaycoPayload(Payment $payment, string $state): array
{
    return [
        'x_id_factura' => $payment->reference,
        'x_transaction_state' => $state,
        'x_ref_payco' => 'pago-'.$payment->id,
        'x_transaction_id' => 'tx-'.$payment->id,
        'x_amount' => (string) ($payment->amount_in_cents / 100),
        'x_currency_code' => 'COP',
        'x_signature' => 'test-signature',
    ];
}

function abogadoConTerminos(): User
{
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    return $user;
}

it('does not activate or notify on a pending webhook, then activates and notifies once on accepted', function (): void {
    Notification::fake();

    $this->mock(EpaycoSignatureService::class, fn ($mock) => $mock
        ->shouldReceive('verifyConfirmationSignature')->andReturn(true));

    $user = abogadoConTerminos();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Premium', 'duration_months' => 3]);
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'reference' => 'REF-PEND-3DS',
        'status' => 'pending',
    ]);

    $this->postJson(route('webhooks.epayco'), epaycoPayload($payment, 'Pendiente'))
        ->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe('pending');
    expect($user->hasActiveSubscription())->toBeFalse();
    Notification::assertNothingSent();

    $this->postJson(route('webhooks.epayco'), epaycoPayload($payment, 'Aceptada'))
        ->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe('approved');
    expect($user->hasActiveSubscription())->toBeTrue();
    Notification::assertSentTimes(PaymentConfirmed::class, 1);
});

it('does not send the confirmation email twice when the approved webhook repeats', function (): void {
    Notification::fake();

    $this->mock(EpaycoSignatureService::class, fn ($mock) => $mock
        ->shouldReceive('verifyConfirmationSignature')->andReturn(true));

    $user = abogadoConTerminos();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Premium', 'duration_months' => 1]);
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'reference' => 'REF-DEDUP',
        'status' => 'pending',
    ]);

    $this->postJson(route('webhooks.epayco'), epaycoPayload($payment, 'Aceptada'))
        ->assertOk();
    $this->postJson(route('webhooks.epayco'), epaycoPayload($payment, 'Aceptada'))
        ->assertOk();

    $payment->refresh();

    expect($payment->status)->toBe('approved');
    expect($user->subscriptions()->count())->toBe(1);
    expect($user->subscriptions()->first()->ends_at->format('Y-m-d'))->toBe(now()->addMonths(1)->format('Y-m-d'));
    Notification::assertSentTimes(PaymentConfirmed::class, 1);
});

it('blocks a new checkout while a valid pending payment exists', function (): void {
    $user = abogadoConTerminos();
    $plan = SubscriptionPlan::factory()->create();
    Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'pending',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('subscription.checkout', $plan))
        ->assertRedirect()
        ->assertSessionHasErrors('subscription');

    expect(Payment::where('user_id', $user->id)->count())->toBe(1);
});

it('returns 409 json when checkout is requested by fetch with a valid pending payment', function (): void {
    $user = abogadoConTerminos();
    $plan = SubscriptionPlan::factory()->create();
    Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'pending',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson(route('subscription.checkout', $plan))
        ->assertStatus(409)
        ->assertJson(['message' => 'Ya tienes un pago en proceso. Te avisaremos por correo en cuanto se confirme.']);

    expect(Payment::where('user_id', $user->id)->count())->toBe(1);
});

it('allows a new checkout when the pending payment is already older than 30 minutes', function (): void {
    $user = abogadoConTerminos();
    $plan = SubscriptionPlan::factory()->create();
    Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'pending',
        'created_at' => now()->subMinutes(45),
    ]);

    $this->actingAs($user)
        ->get(route('subscription.checkout', $plan))
        ->assertOk();

    expect(Payment::where('user_id', $user->id)->count())->toBe(2);
});

it('status returns payment_status pending for a pending payment reference', function (): void {
    $user = abogadoConTerminos();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'reference' => 'REF-STATUS-PENDING',
    ]);

    $this->actingAs($user)
        ->getJson(route('subscription.status', ['reference' => $payment->reference]))
        ->assertOk()
        ->assertJson([
            'active' => false,
            'payment_status' => 'pending',
        ]);
});

it('status returns active true and payment_status approved for an approved payment', function (): void {
    $user = abogadoConTerminos();
    $plan = SubscriptionPlan::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'ends_at' => now()->addMonths(2),
    ]);
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'approved',
        'reference' => 'REF-STATUS-APPROVED',
    ]);

    $this->actingAs($user)
        ->getJson(route('subscription.status', ['reference' => $payment->reference]))
        ->assertOk()
        ->assertJson([
            'active' => true,
            'payment_status' => 'approved',
        ]);
});

it('status does not leak another user pending payment', function (): void {
    $user = abogadoConTerminos();
    $other = abogadoConTerminos();

    $payment = Payment::factory()->create([
        'user_id' => $other->id,
        'status' => 'approved',
        'reference' => 'REF-AJENO',
    ]);

    $this->actingAs($user)
        ->getJson(route('subscription.status', ['reference' => $payment->reference]))
        ->assertOk()
        ->assertJson([
            'active' => false,
            'payment_status' => null,
        ]);
});

it('status without a reference keeps the original response shape', function (): void {
    $user = abogadoConTerminos();

    $this->actingAs($user)
        ->getJson(route('subscription.status'))
        ->assertOk()
        ->assertExactJson(['active' => false]);
});

it('subscribe page exposes the valid pending payment to the view', function (): void {
    $user = abogadoConTerminos();
    SubscriptionPlan::factory()->create(['is_active' => true]);
    Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('subscription.show'));

    $response->assertOk()->assertViewHas('pendingPayment');
    expect($response->viewData('pendingPayment'))->not->toBeNull();
});

it('subscribe page exposes null pending payment when none is valid', function (): void {
    $user = abogadoConTerminos();
    SubscriptionPlan::factory()->create(['is_active' => true]);

    $response = $this->actingAs($user)->get(route('subscription.show'));

    $response->assertOk()->assertViewHas('pendingPayment');
    expect($response->viewData('pendingPayment'))->toBeNull();
});

it('return page starts in pendingConfirmation when the transaction is pending', function (): void {
    $user = abogadoConTerminos();

    $response = $this->actingAs($user)
        ->get(route('subscription.return', ['x_transaction_state' => 'Pendiente', 'x_id_factura' => 'REF-3DS']));

    $response->assertOk()
        ->assertSee('Tu pago está en proceso de verificación de seguridad')
        ->assertSee("paymentReturn('pendingConfirmation', 'REF-3DS')", false);
});

it('return page starts in checking by default', function (): void {
    $user = abogadoConTerminos();

    $response = $this->actingAs($user)->get(route('subscription.return'));

    $response->assertOk()
        ->assertSee("paymentReturn('checking', '')", false);
});

it('return page starts in failed for a rejected transaction', function (): void {
    $user = abogadoConTerminos();

    $response = $this->actingAs($user)
        ->get(route('subscription.return', ['x_response' => 'Rechazada']));

    $response->assertOk()
        ->assertSee("paymentReturn('failed', '')", false);
});

it('show page shows the pending banner and disables the pay button', function (): void {
    $user = abogadoConTerminos();
    $plan = SubscriptionPlan::factory()->create(['is_active' => true, 'name' => 'Premium']);
    Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'pending',
        'reference' => 'REF-BANNER',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('subscription.show'));

    $response->assertOk()
        ->assertSee('Ya tienes un pago en proceso')
        ->assertSee('Te avisaremos por correo en cuanto se confirme')
        ->assertSee('Comprobar estado')
        ->assertSee('Pago en proceso')
        ->assertDontSee('Pagar con PSE');
});

it('show page keeps the pay button when there is no pending payment', function (): void {
    $user = abogadoConTerminos();
    SubscriptionPlan::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('subscription.show'))
        ->assertOk()
        ->assertSee('Pagar con PSE')
        ->assertDontSee('Ya tienes un pago en proceso');
});
