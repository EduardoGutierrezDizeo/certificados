<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

it('shows active subscription details for lawyer', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $plan = SubscriptionPlan::factory()->create(['name' => 'Premium', 'price_in_cents' => 7500000]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'plan' => 'Premium',
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonths(2),
    ]);

    $this->actingAs($user)
        ->get(route('subscription.manage'))
        ->assertOk()
        ->assertSee('Suscripción activa')
        ->assertSee('Premium')
        ->assertSee('75.000')
        ->assertSee(Carbon::parse(now()->subMonth())->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))
        ->assertSee(Carbon::parse(now()->addMonths(2))->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))
        ->assertSee('Cancelar suscripción');
});

it('shows no subscription message when lawyer has never subscribed', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->get(route('subscription.manage'))
        ->assertOk()
        ->assertSee('Aún no tienes una suscripción')
        ->assertSee('Ver planes disponibles')
        ->assertSee(route('subscription.show'));
});

it('shows cancelled subscription message', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    Subscription::factory()->cancelled()->create([
        'user_id' => $user->id,
        'ends_at' => now()->subDays(10),
    ]);

    $this->actingAs($user)
        ->get(route('subscription.manage'))
        ->assertOk()
        ->assertSee('Suscripción cancelada')
        ->assertSee(Carbon::parse(now()->subDays(10))->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))
        ->assertSee('Elegir un plan nuevo')
        ->assertDontSee('Cancelar suscripción');
});

it('shows expired subscription message', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    Subscription::factory()->expired()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('subscription.manage'))
        ->assertOk()
        ->assertSee('Suscripción vencida')
        ->assertSee('Elegir un plan nuevo')
        ->assertDontSee('Cancelar suscripción');
});

it('cancel action marks subscription as cancelled and redirects', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'ends_at' => now()->addMonths(2),
    ]);

    $this->actingAs($user)
        ->post(route('subscription.cancel'))
        ->assertRedirect(route('subscription.manage'));

    expect($subscription->fresh()->status)->toBe('cancelled');
    expect($user->hasActiveSubscription())->toBeFalse();
});

it('cancel action redirects with error when no active subscription', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->post(route('subscription.cancel'))
        ->assertRedirect();
});

it('unauthenticated user cannot access manage page', function (): void {
    $this->get(route('subscription.manage'))
        ->assertRedirect('/login');
});

it('admin cannot access manage page via abogado route', function (): void {
    $admin = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('subscription.manage'))
        ->assertForbidden();
});
