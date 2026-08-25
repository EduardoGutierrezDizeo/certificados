<?php

use App\Models\Subscription;
use App\Models\User;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

it('cancel changes status to cancelled and ends_at to now', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'ends_at' => now()->addMonths(3),
    ]);

    $subscription->cancel();

    expect($subscription->fresh()->status)->toBe('cancelled');
    expect($subscription->fresh()->ends_at->isToday())->toBeTrue();
});

it('cancelled subscription is no longer considered active', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'ends_at' => now()->addMonths(3),
    ]);

    expect($user->hasActiveSubscription())->toBeTrue();

    $user->currentSubscription()->cancel();

    expect($user->hasActiveSubscription())->toBeFalse();
});

it('currentSubscription returns most recent subscription regardless of status', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $oldSubscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'starts_at' => now()->subMonths(3),
        'ends_at' => now()->subMonth(),
    ]);

    $currentSubscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $result = $user->currentSubscription();

    expect($result->id)->toBe($currentSubscription->id);
});

it('middleware blocks access after cancellation', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'ends_at' => now()->addMonths(3),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $user->currentSubscription()->cancel();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('subscription.show'));
});

it('middleware still allows subscription routes after cancellation', function (): void {
    $user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'ends_at' => now()->addMonths(3),
    ]);

    $user->currentSubscription()->cancel();

    $this->actingAs($user)
        ->get(route('subscription.show'))
        ->assertOk();
});
