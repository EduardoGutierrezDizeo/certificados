<?php

use App\Models\Payment;
use App\Models\User;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

it('expires pending payments older than 30 minutes and leaves recent ones pending', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $viejo = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'created_at' => now()->subMinutes(45),
    ]);
    $reciente = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'created_at' => now()->subMinutes(10),
    ]);

    $this->artisan('payments:expire-pending')
        ->assertExitCode(0);

    expect($viejo->fresh()->status)->toBe('declined');
    expect($viejo->fresh()->raw_payload)->toHaveKey('expired_automatically_at');
    expect($viejo->fresh()->raw_payload)->toHaveKey('expired_automatically_reason');
    expect($reciente->fresh()->status)->toBe('pending');
});

it('expiration preserves the original ePayco payload when one exists', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'created_at' => now()->subHours(2),
        'raw_payload' => ['x_ref_payco' => 'previo'],
    ]);

    $this->artisan('payments:expire-pending')
        ->assertExitCode(0);

    $payload = $payment->fresh()->raw_payload;

    expect($payload['x_ref_payco'])->toBe('previo');
    expect($payload)->toHaveKey('expired_automatically_at');
});

it('does not touch payments that are not pending', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $approved = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'approved',
        'created_at' => now()->subHours(2),
    ]);
    $declined = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'declined',
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('payments:expire-pending')
        ->assertExitCode(0);

    expect($approved->fresh()->status)->toBe('approved');
    expect($declined->fresh()->status)->toBe('declined');
});
