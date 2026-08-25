<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

it('allows admin to view the create lawyer form', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    SubscriptionPlan::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.lawyers.create'))
        ->assertOk();
});

it('shows plan select on create form when active plans exist', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plan = SubscriptionPlan::factory()->create(['name' => 'Premium', 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('admin.lawyers.create'))
        ->assertOk()
        ->assertSee('Premium')
        ->assertSee('subscription_plan_id');
});

it('shows no-plans message when no active plans exist', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    SubscriptionPlan::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('admin.lawyers.create'))
        ->assertOk()
        ->assertSee('No hay planes de suscripción activos');
});

it('creates a lawyer with a valid subscription plan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Premium',
        'duration_months' => 3,
        'price_in_cents' => 7500000,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.lawyers.store'), [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'subscription_plan_id' => $plan->id,
        ])
        ->assertRedirect();

    $lawyer = User::where('email', 'juan@example.com')->first();
    expect($lawyer)->not->toBeNull();
    expect($lawyer->hasRole('abogado'))->toBeTrue();

    $subscription = Subscription::where('user_id', $lawyer->id)->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->subscription_plan_id)->toBe($plan->id);
    expect($subscription->plan)->toBe('Premium');
    expect($subscription->status)->toBe('active');
    expect($subscription->starts_at->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
    expect($subscription->ends_at->format('Y-m-d'))->toBe(now()->addMonths(3)->format('Y-m-d'));
});

it('creates an admin grant payment when creating a lawyer', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Premium',
        'duration_months' => 3,
        'price_in_cents' => 7500000,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.lawyers.store'), [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'subscription_plan_id' => $plan->id,
        ])
        ->assertRedirect();

    $lawyer = User::where('email', 'juan@example.com')->first();

    $payment = Payment::where('user_id', $lawyer->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->subscription_plan_id)->toBe($plan->id);
    expect($payment->payment_provider)->toBe('admin_grant');
    expect($payment->amount_in_cents)->toBe(0);
    expect($payment->status)->toBe('approved');
    expect($payment->reference)->toStartWith('ADMIN-GRANT-'.$lawyer->id);
    expect($payment->raw_payload['granted_by_admin_id'])->toBe($admin->id);
    expect($payment->raw_payload['reason'])->toBe('Cuenta creada manualmente por administrador');
});

it('rejects creating a lawyer without a subscription plan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.lawyers.store'), [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
        ])
        ->assertSessionHasErrors('subscription_plan_id');
});

it('rejects an inactive subscription plan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $inactivePlan = SubscriptionPlan::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->post(route('admin.lawyers.store'), [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'subscription_plan_id' => $inactivePlan->id,
        ])
        ->assertSessionHasErrors('subscription_plan_id');
});

it('rejects a non-existent subscription plan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.lawyers.store'), [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'subscription_plan_id' => 99999,
        ])
        ->assertSessionHasErrors('subscription_plan_id');
});

it('does not allow abogados to access lawyer management routes', function (): void {
    $lawyer = User::factory()->create();
    $lawyer->assignRole('abogado');

    $plan = SubscriptionPlan::factory()->create();

    $this->actingAs($lawyer)
        ->get(route('admin.lawyers.create'))
        ->assertForbidden();

    $this->actingAs($lawyer)
        ->post(route('admin.lawyers.store'), [
            'name' => 'Test',
            'email' => 'test@test.com',
            'subscription_plan_id' => $plan->id,
        ])
        ->assertForbidden();
});
