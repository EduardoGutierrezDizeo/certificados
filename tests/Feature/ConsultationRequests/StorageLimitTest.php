<?php

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LawyerStorageService;
use Illuminate\Support\Facades\Redis;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'current_session_id' => null,
        'must_change_password' => false,
        'terms_version_accepted' => config('legal.terms_version'),
    ]);
    $this->user->assignRole('abogado');

    $plan = SubscriptionPlan::factory()->create();
    Subscription::factory()->create([
        'user_id' => $this->user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($this->user);

    Redis::shouldReceive('rpush');
});

/**
 * Crea una consulta (con el subject propio del abogado) y N certificados
 * exitosos con pdf_path, usando timestamps pdf_generated_at decrecientes
 * para controlar el orden de antiguedad. Devuelve el subject creado.
 */
function seedStorageCertificates(User $lawyer, int $count): Subject
{
    $subject = Subject::create([
        'lawyer_id' => $lawyer->id,
        'document_type' => 'CC',
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'full_name' => 'Persona '.$lawyer->id,
    ]);

    $sites = ['comptroller', 'judicial_police', 'rnmc', 'attorney_general'];
    $created = 0;

    while ($created < $count) {
        $consultation = ConsultationRequest::create([
            'lawyer_id' => $lawyer->id,
            'subject_id' => $subject->id,
            'status' => 'success',
        ]);

        foreach ($sites as $site) {
            if ($created >= $count) {
                break;
            }

            CertificateRequest::create([
                'consultation_request_id' => $consultation->id,
                'site' => $site,
                'status' => 'success',
                'pdf_path' => "certificates/{$consultation->id}/{$site}.pdf",
                'pdf_generated_at' => now()->subMinutes($count - $created),
            ]);

            $created++;
        }
    }

    return $subject;
}

it('creates a consultation normally when there is storage space', function (): void {
    seedStorageCertificates($this->user, 3);

    $response = $this->post(route('consultation-requests.store'), [
        'document_type' => 'CC',
        'document_number' => '111222333',
        'issuance_date' => '1990-01-01',
        'sites' => ['rnmc'],
    ]);

    $response->assertRedirect();

    $consultation = ConsultationRequest::firstWhere('status', 'pending');

    expect($consultation)->not->toBeNull()
        ->and($consultation->certificateRequests()->where('site', 'rnmc')->exists())->toBeTrue();
});

it('creation without space and without confirmation returns 409 with the oldest candidates', function (): void {
    seedStorageCertificates($this->user, 500);

    $service = app(LawyerStorageService::class);

    expect($service->usedCount($this->user))->toBe(500);

    $expectedOldest = $service->oldestCertificatesToFree($this->user, 1)->first();

    $response = $this->post(route('consultation-requests.store'), [
        'document_type' => 'CC',
        'document_number' => '111222333',
        'issuance_date' => '1990-01-01',
        'sites' => ['rnmc'],
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'needs_confirmation' => true,
        ]);

    $payload = $response->json('to_delete');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['site'])->toBe($expectedOldest->site)
        ->and($payload[0]['document_number'])->toBe($expectedOldest->consultationRequest->subject->document_number)
        ->and($payload[0]['pdf_generated_at'])->not->toBeNull();

    expect(ConsultationRequest::count())->toBe(125)
        ->and($service->usedCount($this->user))->toBe(500);
});

it('creation without space and with confirmation frees the oldest and creates the consultation', function (): void {
    seedStorageCertificates($this->user, 500);

    $service = app(LawyerStorageService::class);

    $expectFreed = $service->oldestCertificatesToFree($this->user, 1);
    $expectFreedIds = $expectFreed->pluck('id');

    $response = $this->post(route('consultation-requests.store'), [
        'document_type' => 'CC',
        'document_number' => '111222333',
        'issuance_date' => '1990-01-01',
        'sites' => ['rnmc'],
        'confirm_delete_oldest' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    foreach ($expectFreedIds as $id) {
        $cert = CertificateRequest::find($id);

        expect($cert->pdf_path)->toBeNull()
            ->and($cert->pdf_generated_at)->toBeNull()
            ->and($cert->status)->toBe('success');
    }

    expect($service->usedCount($this->user))->toBe(499)
        ->and(ConsultationRequest::where('status', 'pending')->exists())->toBeTrue();
});

it('regeneration without space excludes the certificates of its own consultation from candidates', function (): void {
    $ownSubject = Subject::create([
        'lawyer_id' => $this->user->id,
        'document_type' => 'CC',
        'document_number' => '999888777',
        'full_name' => 'Propia Consulta',
    ]);

    $ownConsultation = ConsultationRequest::create([
        'lawyer_id' => $this->user->id,
        'subject_id' => $ownSubject->id,
        'status' => 'success',
    ]);

    $ownOldestCert = CertificateRequest::create([
        'consultation_request_id' => $ownConsultation->id,
        'site' => 'rnmc',
        'status' => 'success',
        'pdf_path' => "certificates/{$ownConsultation->id}/rnmc.pdf",
        'pdf_generated_at' => now()->subDays(10),
    ]);

    $targetCert = CertificateRequest::create([
        'consultation_request_id' => $ownConsultation->id,
        'site' => 'comptroller',
        'status' => 'success',
        'pdf_path' => null,
    ]);

    seedStorageCertificates($this->user, 500);

    $service = app(LawyerStorageService::class);

    expect($service->usedCount($this->user))->toBe(501);

    $expectedCandidates = $service->oldestCertificatesToFree($this->user, 1, $ownConsultation->id);
    $expected = $expectedCandidates->first();

    $response = $this->post(route('consultation-requests.certificates.regenerate', [
        'consultationRequest' => $ownConsultation,
        'certificateRequest' => $targetCert,
    ]));

    $response->assertStatus(409)
        ->assertJson(['needs_confirmation' => true]);

    $payload = $response->json('to_delete');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['document_number'])->toBe($expected->consultationRequest->subject->document_number)
        ->and($payload[0]['site'])->toBe($expected->site);

    expect($ownOldestCert->fresh()->pdf_path)->not->toBeNull();
});

it('regeneration of a certificate that already has a pdf_path returns 422', function (): void {
    $consultation = ConsultationRequest::create([
        'lawyer_id' => $this->user->id,
        'subject_id' => Subject::create([
            'lawyer_id' => $this->user->id,
            'document_type' => 'CC',
            'document_number' => '555666777',
            'full_name' => 'Con PDF',
        ])->id,
        'status' => 'success',
    ]);

    $cert = CertificateRequest::create([
        'consultation_request_id' => $consultation->id,
        'site' => 'rnmc',
        'status' => 'success',
        'pdf_path' => "certificates/{$consultation->id}/rnmc.pdf",
        'pdf_generated_at' => now(),
    ]);

    $response = $this->post(route('consultation-requests.certificates.regenerate', [
        'consultationRequest' => $consultation,
        'certificateRequest' => $cert,
    ]));

    $response->assertStatus(422);
});
