<?php

use App\Models\SubscriptionPlan;
use App\Models\User;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

it('allows admin to view subscription plans index', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    SubscriptionPlan::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.subscription-plans.index'))
        ->assertOk();
});

it('allows admin to view create form', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.subscription-plans.create'))
        ->assertOk();
});

it('allows admin to create a valid subscription plan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.subscription-plans.store'), [
            'name' => 'Premium',
            'price_in_cents' => 50000,
            'duration_months' => 1,
            'description' => 'Plan premium con acceso completo',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subscription_plans', [
        'name' => 'Premium',
        'price_in_cents' => 5000000,
        'duration_months' => 1,
        'is_active' => true,
    ]);
});

it('validates name is required', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.subscription-plans.store'), [
            'name' => '',
            'price_in_cents' => 50000,
            'duration_months' => 1,
        ])
        ->assertSessionHasErrors('name');
});

it('validates price_in_cents is required and non-negative', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.subscription-plans.store'), [
            'name' => 'Test',
            'price_in_cents' => -100,
            'duration_months' => 1,
        ])
        ->assertSessionHasErrors('price_in_cents');
});

it('validates duration_months minimum is 1', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.subscription-plans.store'), [
            'name' => 'Test',
            'price_in_cents' => 50000,
            'duration_months' => 0,
        ])
        ->assertSessionHasErrors('duration_months');
});

it('allows admin to edit a subscription plan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plan = SubscriptionPlan::factory()->create(['name' => 'Original']);

    $this->actingAs($admin)
        ->get(route('admin.subscription-plans.edit', $plan))
        ->assertOk()
        ->assertSee('Original');
});

it('allows admin to update a subscription plan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Old Name',
        'price_in_cents' => 3000000,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.subscription-plans.update', $plan), [
            'name' => 'New Name',
            'price_in_cents' => 75000,
            'duration_months' => 3,
            'description' => 'Updated description',
            'is_active' => 0,
        ])
        ->assertRedirect();

    $plan->refresh();

    expect($plan->name)->toBe('New Name');
    expect($plan->price_in_cents)->toBe(7500000);
    expect($plan->duration_months)->toBe(3);
    expect($plan->description)->toBe('Updated description');
    expect($plan->is_active)->toBeFalse();
});

it('allows admin to toggle plan active status', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.subscription-plans.update', $plan), [
            'name' => $plan->name,
            'price_in_cents' => $plan->price_in_cents / 100,
            'duration_months' => $plan->duration_months,
            'is_active' => 0,
        ])
        ->assertRedirect();

    $plan->refresh();
    expect($plan->is_active)->toBeFalse();
});

it('does not allow abogados to access subscription plan routes', function (): void {
    $lawyer = User::factory()->create();
    $lawyer->assignRole('abogado');

    $plan = SubscriptionPlan::factory()->create();

    $this->actingAs($lawyer)
        ->get(route('admin.subscription-plans.index'))
        ->assertForbidden();

    $this->actingAs($lawyer)
        ->get(route('admin.subscription-plans.create'))
        ->assertForbidden();

    $this->actingAs($lawyer)
        ->post(route('admin.subscription-plans.store'), [
            'name' => 'Test',
            'price_in_cents' => 50000,
            'duration_months' => 1,
        ])
        ->assertForbidden();

    $this->actingAs($lawyer)
        ->get(route('admin.subscription-plans.edit', $plan))
        ->assertForbidden();

    $this->actingAs($lawyer)
        ->put(route('admin.subscription-plans.update', $plan), [
            'name' => 'Test',
            'price_in_cents' => 50000,
            'duration_months' => 1,
            'is_active' => 1,
        ])
        ->assertForbidden();
});
