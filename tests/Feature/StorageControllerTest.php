<?php

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LawyerStorageService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

beforeEach(function (): void {
    Storage::fake('local');

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
});

function storagePanelSubject(User $lawyer, string $docNumber): Subject
{
    return Subject::create([
        'lawyer_id' => $lawyer->id,
        'document_type' => 'CC',
        'document_number' => $docNumber,
        'full_name' => 'Persona '.substr($docNumber, -4),
    ]);
}

function storagePanelConsultation(User $lawyer, Subject $subject): ConsultationRequest
{
    return ConsultationRequest::create([
        'lawyer_id' => $lawyer->id,
        'subject_id' => $subject->id,
        'status' => 'success',
    ]);
}

function storagePanelCertificate(ConsultationRequest $consultation, string $site, bool $withFile = true): CertificateRequest
{
    return CertificateRequest::create([
        'consultation_request_id' => $consultation->id,
        'site' => $site,
        'status' => 'success',
        'pdf_path' => $withFile ? "certificates/{$consultation->id}/{$site}.pdf" : null,
        'pdf_generated_at' => now(),
    ]);
}

it('grouped view shows complete and incomplete consultations correctly', function (): void {
    $subject = storagePanelSubject($this->user, '111111111');

    $complete = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($complete, 'rnmc');
    storagePanelCertificate($complete, 'comptroller');

    $incomplete = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($incomplete, 'rnmc');
    storagePanelCertificate($incomplete, 'comptroller', false);

    $response = $this->get(route('storage.index', ['view' => 'grouped']));

    $response->assertOk()
        ->assertSee('Completo')
        ->assertSee('Incompleto');
});

it('a lawyer cannot see another lawyer storage data', function (): void {
    $other = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $other->assignRole('abogado');

    $subject = storagePanelSubject($other, '999999999');
    $consultation = storagePanelConsultation($other, $subject);
    storagePanelCertificate($consultation, 'rnmc');

    $response = $this->get(route('storage.index', ['view' => 'grouped']));

    $response->assertOk()
        ->assertDontSee('999999999');
});

it('a lawyer cannot delete another lawyer certificate (403)', function (): void {
    $other = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $other->assignRole('abogado');

    $subject = storagePanelSubject($other, '999999991');
    $consultation = storagePanelConsultation($other, $subject);
    $cert = storagePanelCertificate($consultation, 'rnmc');

    $this->delete(route('storage.certificates.destroy', $cert))->assertForbidden();

    expect($cert->fresh()->pdf_path)->not->toBeNull();
});

it('destroyCertificate frees storage space', function (): void {
    $subject = storagePanelSubject($this->user, '222222222');
    $consultation = storagePanelConsultation($this->user, $subject);

    $cert1 = storagePanelCertificate($consultation, 'rnmc');
    $cert2 = storagePanelCertificate($consultation, 'comptroller');

    $service = app(LawyerStorageService::class);

    expect($service->usedCount($this->user))->toBe(2);

    $this->delete(route('storage.certificates.destroy', $cert1))
        ->assertRedirect(route('storage.index'));

    expect($service->usedCount($this->user))->toBe(1)
        ->and($cert1->fresh()->pdf_path)->toBeNull()
        ->and($cert2->fresh()->pdf_path)->not->toBeNull();
});

it('destroyConsultation frees all pdf_path of the consultation', function (): void {
    $subject = storagePanelSubject($this->user, '333333333');
    $consultation = storagePanelConsultation($this->user, $subject);

    $cert1 = storagePanelCertificate($consultation, 'rnmc');
    $cert2 = storagePanelCertificate($consultation, 'comptroller');
    $cert3 = storagePanelCertificate($consultation, 'judicial_police');

    $service = app(LawyerStorageService::class);

    expect($service->usedCount($this->user))->toBe(3);

    $this->delete(route('storage.consultations.destroy', $consultation))
        ->assertRedirect(route('storage.index'));

    expect($service->usedCount($this->user))->toBe(0)
        ->and($cert1->fresh()->pdf_path)->toBeNull()
        ->and($cert2->fresh()->pdf_path)->toBeNull()
        ->and($cert3->fresh()->pdf_path)->toBeNull();
});

it('destroyCertificatesBulk rejects ids that do not belong to the authenticated lawyer', function (): void {
    $other = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $other->assignRole('abogado');

    $otherSubject = storagePanelSubject($other, '999999992');
    $otherConsultation = storagePanelConsultation($other, $otherSubject);
    $foreignCert = storagePanelCertificate($otherConsultation, 'rnmc');

    $ownSubject = storagePanelSubject($this->user, '444444444');
    $ownConsultation = storagePanelConsultation($this->user, $ownSubject);
    $ownCert = storagePanelCertificate($ownConsultation, 'rnmc');

    $response = $this->delete(route('storage.certificates.destroy-bulk'), [
        'ids' => [$ownCert->id, $foreignCert->id],
    ]);

    $response->assertForbidden();

    expect($ownCert->fresh()->pdf_path)->not->toBeNull()
        ->and($foreignCert->fresh()->pdf_path)->not->toBeNull();
});
