<?php

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

beforeEach(function (): void {
    Notification::fake();
});

it('sends notification to subscription expiring in exactly 3 days', function (): void {
    $user = User::factory()->create();
    $user->assignRole('abogado');

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'Premium',
        'status' => 'active',
        'ends_at' => now()->addDays(3),
        'expiry_notified_at' => null,
    ]);

    $this->artisan('subscriptions:notify-expiring')
        ->assertExitCode(0);

    Notification::assertSentTo(
        $user,
        SubscriptionExpiringSoon::class
    );

    expect($subscription->fresh()->expiry_notified_at)->not->toBeNull();
});

it('does not send notification to subscription expiring in 2 days', function (): void {
    $user = User::factory()->create();
    $user->assignRole('abogado');

    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'ends_at' => now()->addDays(2),
        'expiry_notified_at' => null,
    ]);

    $this->artisan('subscriptions:notify-expiring')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

it('does not send notification to subscription expiring in 4 days', function (): void {
    $user = User::factory()->create();
    $user->assignRole('abogado');

    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'ends_at' => now()->addDays(4),
        'expiry_notified_at' => null,
    ]);

    $this->artisan('subscriptions:notify-expiring')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

it('does not send notification twice to same subscription', function (): void {
    $user = User::factory()->create();
    $user->assignRole('abogado');

    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'ends_at' => now()->addDays(3),
        'expiry_notified_at' => null,
    ]);

    $this->artisan('subscriptions:notify-expiring')
        ->assertExitCode(0);

    Notification::assertSentTo(
        $user,
        SubscriptionExpiringSoon::class
    );

    Notification::fake();

    $this->artisan('subscriptions:notify-expiring')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

it('does not send notification to cancelled subscription', function (): void {
    $user = User::factory()->create();
    $user->assignRole('abogado');

    Subscription::factory()->cancelled()->create([
        'user_id' => $user->id,
        'ends_at' => now()->addDays(3),
        'expiry_notified_at' => null,
    ]);

    $this->artisan('subscriptions:notify-expiring')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

it('notification email does not contain action button or link', function (): void {
    $user = User::factory()->create();
    $user->assignRole('abogado');

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'Premium',
        'status' => 'active',
        'ends_at' => now()->addDays(3),
    ]);

    $notification = new SubscriptionExpiringSoon($subscription);
    $mail = $notification->toMail($user);

    expect($mail->actionText)->toBeNull();
    expect($mail->actionUrl)->toBeNull();
});

it('notification mentions the correct plan name', function (): void {
    $user = User::factory()->create();
    $user->assignRole('abogado');

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'Premium',
        'status' => 'active',
        'ends_at' => now()->addDays(3),
    ]);

    $notification = new SubscriptionExpiringSoon($subscription);
    $mail = $notification->toMail($user);

    $emailBody = $mail->render();
    expect(str_contains($emailBody, 'Premium'))->toBeTrue();
});
