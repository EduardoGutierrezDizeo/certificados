<?php

use App\Models\ErrorReport;
use App\Models\User;
use App\Notifications\ErrorReportResolved;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

it('allows admin to view error reports index', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    ErrorReport::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.error-reports.index'))
        ->assertOk();
});

it('allows admin to view error report detail', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $report = ErrorReport::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.error-reports.show', $report))
        ->assertOk();
});

it('allows admin to resolve a pending error report', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $report = ErrorReport::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->patch(route('admin.error-reports.resolve', $report), [
            'admin_comment' => 'Se corrigio el problema.',
        ])
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe('resolved');
    expect($report->admin_comment)->toBe('Se corrigio el problema.');
    expect($report->resolved_by)->toBe($admin->id);
    expect($report->resolved_at)->not->toBeNull();
});

it('allows admin to resolve without a comment', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $report = ErrorReport::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->patch(route('admin.error-reports.resolve', $report))
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe('resolved');
    expect($report->admin_comment)->toBeNull();
    expect($report->resolved_by)->toBe($admin->id);
});

it('rejects resolving an already resolved report', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $report = ErrorReport::factory()->create(['status' => 'resolved']);

    $this->actingAs($admin)
        ->patch(route('admin.error-reports.resolve', $report))
        ->assertStatus(422);
});

it('does not allow abogados to access admin error reports', function (): void {
    $lawyer = User::factory()->create();
    $lawyer->assignRole('abogado');

    $report = ErrorReport::factory()->create();

    $this->actingAs($lawyer)
        ->get(route('admin.error-reports.index'))
        ->assertForbidden();

    $this->actingAs($lawyer)
        ->get(route('admin.error-reports.show', $report))
        ->assertForbidden();

    $this->actingAs($lawyer)
        ->patch(route('admin.error-reports.resolve', $report))
        ->assertForbidden();
});

it('redirects unauthenticated users to login', function (): void {
    $report = ErrorReport::factory()->create();

    $this->get(route('admin.error-reports.index'))
        ->assertRedirect('/login');

    $this->get(route('admin.error-reports.show', $report))
        ->assertRedirect('/login');

    $this->patch(route('admin.error-reports.resolve', $report))
        ->assertRedirect('/login');
});

it('shows the resolve form for pending reports', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $report = ErrorReport::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->get(route('admin.error-reports.show', $report))
        ->assertOk()
        ->assertSee('Marcar como resuelto')
        ->assertSee('admin_comment');
});

it('does not show the resolve form for resolved reports', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $report = ErrorReport::factory()->create(['status' => 'resolved']);

    $this->actingAs($admin)
        ->get(route('admin.error-reports.show', $report))
        ->assertOk()
        ->assertDontSee('Marcar como resuelto')
        ->assertSee('Resolucion');
});

it('sends notification to the lawyer when report is resolved', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $lawyer = User::factory()->create();
    $lawyer->assignRole('abogado');

    $report = ErrorReport::factory()->create([
        'lawyer_id' => $lawyer->id,
        'status' => 'pending',
        'subject' => 'No me funciona la descarga',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.error-reports.resolve', $report), [
            'admin_comment' => 'Ya se corrigió el problema.',
        ]);

    Notification::assertSentTo($lawyer, ErrorReportResolved::class);
});

it('marks report as resolved even when notification fails', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $lawyer = User::factory()->create();
    $lawyer->assignRole('abogado');

    $report = ErrorReport::factory()->create([
        'lawyer_id' => $lawyer->id,
        'status' => 'pending',
    ]);

    Notification::assertNothingSent();

    Notification::fake([ErrorReportResolved::class => false]);

    $this->actingAs($admin)
        ->patch(route('admin.error-reports.resolve', $report), [
            'admin_comment' => 'Corregido.',
        ])
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe('resolved');
    expect($report->admin_comment)->toBe('Corregido.');
    expect($report->resolved_by)->toBe($admin->id);
});

it('allows admin to resend notification for resolved reports', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $lawyer = User::factory()->create();
    $lawyer->assignRole('abogado');

    $report = ErrorReport::factory()->create([
        'lawyer_id' => $lawyer->id,
        'status' => 'resolved',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.error-reports.resend-notification', $report))
        ->assertRedirect();

    Notification::assertSentTo($lawyer, ErrorReportResolved::class);
});

it('rejects resend notification for pending reports', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $report = ErrorReport::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->post(route('admin.error-reports.resend-notification', $report))
        ->assertStatus(422);
});

it('does not allow abogados to resend notifications', function (): void {
    Notification::fake();

    $lawyer = User::factory()->create();
    $lawyer->assignRole('abogado');

    $report = ErrorReport::factory()->create([
        'lawyer_id' => $lawyer->id,
        'status' => 'resolved',
    ]);

    $this->actingAs($lawyer)
        ->post(route('admin.error-reports.resend-notification', $report))
        ->assertForbidden();
});

it('shows resend button on resolved report detail', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $report = ErrorReport::factory()->create(['status' => 'resolved']);

    $this->actingAs($admin)
        ->get(route('admin.error-reports.show', $report))
        ->assertOk()
        ->assertSee('Reenviar notificación por correo')
        ->assertSee(route('admin.error-reports.resend-notification', $report));
});
