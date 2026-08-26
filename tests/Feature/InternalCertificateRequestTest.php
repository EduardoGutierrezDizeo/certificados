<?php

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

beforeEach(function (): void {
    Storage::fake('local');

    config(['services.internal_api.key' => 'test-internal-key-12345']);

    $this->user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $this->user->assignRole('abogado');

    $this->subject = Subject::create([
        'lawyer_id' => $this->user->id,
        'document_type' => 'CC',
        'document_number' => '1234567890',
        'full_name' => 'Juan Perez',
    ]);

    $this->consultation = ConsultationRequest::create([
        'lawyer_id' => $this->user->id,
        'subject_id' => $this->subject->id,
        'status' => 'pending',
    ]);

    $this->cert = CertificateRequest::create([
        'consultation_request_id' => $this->consultation->id,
        'site' => 'rnmc',
        'status' => 'pending',
    ]);
});

it('complete saves duration_seconds when provided', function (): void {
    $response = $this->postJson(
        "/api/internal/certificate-requests/{$this->cert->id}/complete",
        [
            'status' => 'failed',
            'error_message' => 'Sitio no disponible',
            'duration_seconds' => 42,
        ],
        ['X-Internal-Api-Key' => 'test-internal-key-12345']
    );

    $response->assertOk();
    expect($this->cert->fresh()->duration_seconds)->toBe(42);
});

it('complete works without duration_seconds', function (): void {
    $response = $this->postJson(
        "/api/internal/certificate-requests/{$this->cert->id}/complete",
        [
            'status' => 'failed',
            'error_message' => 'Error de conexión',
        ],
        ['X-Internal-Api-Key' => 'test-internal-key-12345']
    );

    $response->assertOk();
    expect($this->cert->fresh()->duration_seconds)->toBeNull();
});

it('complete saves duration_seconds on success', function (): void {
    $response = $this->postJson(
        "/api/internal/certificate-requests/{$this->cert->id}/complete",
        [
            'status' => 'success',
            'duration_seconds' => 15,
            'pdf' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
        ],
        ['X-Internal-Api-Key' => 'test-internal-key-12345']
    );

    $response->assertOk();
    expect($this->cert->fresh()->duration_seconds)->toBe(15);
});

it('complete rejects without internal api key', function (): void {
    $response = $this->postJson(
        "/api/internal/certificate-requests/{$this->cert->id}/complete",
        [
            'status' => 'failed',
            'error_message' => 'Error',
        ]
    );

    $response->assertUnauthorized();
});
