<?php

use App\Models\User;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

it('shows the error report form to abogados', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->get(route('error-reports.create'))
        ->assertOk();
});

it('creates an error report with valid data', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->post(route('error-reports.store'), [
            'subject' => 'No puedo descargar el certificado',
            'description' => 'Al intentar descargar me sale error 500.',
            'category' => 'certificado',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('error_reports', [
        'lawyer_id' => $user->id,
        'subject' => 'No puedo descargar el certificado',
        'category' => 'certificado',
        'status' => 'pending',
    ]);
});

it('validates required fields', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->post(route('error-reports.store'), [])
        ->assertSessionHasErrors(['subject', 'description', 'category']);
});

it('validates category is one of the allowed values', function (): void {
    $user = User::factory()->create(['terms_version_accepted' => config('legal.terms_version')]);
    $user->assignRole('abogado');

    $this->actingAs($user)
        ->post(route('error-reports.store'), [
            'subject' => 'Test',
            'description' => 'Test description',
            'category' => 'invalid_category',
        ])
        ->assertSessionHasErrors('category');
});

it('does not allow admins to access the form', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('error-reports.create'))
        ->assertForbidden();
});

it('redirects unauthenticated users to login', function (): void {
    $this->get(route('error-reports.create'))
        ->assertRedirect('/login');
});
