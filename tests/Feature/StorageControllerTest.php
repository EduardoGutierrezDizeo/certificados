<?php

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LawyerStorageService;
use Illuminate\Database\Eloquent\Collection;
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

it('grouped view sorts consultations by date descending by default', function (): void {
    $olderSubject = storagePanelSubject($this->user, '600000001');
    $newerSubject = storagePanelSubject($this->user, '600000002');

    $older = storagePanelConsultation($this->user, $olderSubject);
    $newer = storagePanelConsultation($this->user, $newerSubject);

    $older->forceFill(['created_at' => now()->subDays(2)])->save();
    $newer->forceFill(['created_at' => now()])->save();

    storagePanelCertificate($older, 'rnmc');
    storagePanelCertificate($newer, 'rnmc');

    $response = $this->get(route('storage.index', ['view' => 'grouped']));

    $response->assertOk()
        ->assertSeeInOrder(['600000002', '600000001']);
});

it('grouped view sorts consultations by date ascending when requested', function (): void {
    $olderSubject = storagePanelSubject($this->user, '600000003');
    $newerSubject = storagePanelSubject($this->user, '600000004');

    $older = storagePanelConsultation($this->user, $olderSubject);
    $newer = storagePanelConsultation($this->user, $newerSubject);

    $older->forceFill(['created_at' => now()->subDays(2)])->save();
    $newer->forceFill(['created_at' => now()])->save();

    storagePanelCertificate($older, 'rnmc');
    storagePanelCertificate($newer, 'rnmc');

    $response = $this->get(route('storage.index', ['view' => 'grouped', 'sort' => 'date', 'dir' => 'asc']));

    $response->assertOk()
        ->assertSeeInOrder(['600000003', '600000004']);
});

it('individual view sorts certificates by pdf_generated_at descending by default', function (): void {
    $olderSubject = storagePanelSubject($this->user, '700000001');
    $newerSubject = storagePanelSubject($this->user, '700000002');

    $olderConsultation = storagePanelConsultation($this->user, $olderSubject);
    $newerConsultation = storagePanelConsultation($this->user, $newerSubject);

    $olderCert = storagePanelCertificate($olderConsultation, 'rnmc');
    $newerCert = storagePanelCertificate($newerConsultation, 'comptroller');

    $olderCert->forceFill(['pdf_generated_at' => now()->subDays(2)])->save();
    $newerCert->forceFill(['pdf_generated_at' => now()])->save();

    $response = $this->get(route('storage.index', ['view' => 'individual']));

    $response->assertOk()
        ->assertSeeInOrder(['700000002', '700000001']);
});

it('individual view sorts certificates by pdf_generated_at ascending when requested', function (): void {
    $olderSubject = storagePanelSubject($this->user, '700000003');
    $newerSubject = storagePanelSubject($this->user, '700000004');

    $olderConsultation = storagePanelConsultation($this->user, $olderSubject);
    $newerConsultation = storagePanelConsultation($this->user, $newerSubject);

    $olderCert = storagePanelCertificate($olderConsultation, 'rnmc');
    $newerCert = storagePanelCertificate($newerConsultation, 'comptroller');

    $olderCert->forceFill(['pdf_generated_at' => now()->subDays(2)])->save();
    $newerCert->forceFill(['pdf_generated_at' => now()])->save();

    $response = $this->get(route('storage.index', ['view' => 'individual', 'sort' => 'date', 'dir' => 'asc']));

    $response->assertOk()
        ->assertSeeInOrder(['700000003', '700000004']);
});

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

it('storage.data returns grouped json with used and limit', function (): void {
    $subject = storagePanelSubject($this->user, '810000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($consultation, 'rnmc');

    $response = $this->getJson(route('storage.data', ['view' => 'grouped']));

    $response->assertOk()
        ->assertJsonPath('view', 'grouped')
        ->assertJsonPath('used', 1)
        ->assertJsonPath('limit', config('certificates.storage_limit'))
        ->assertJsonCount(1, 'data');
});

it('storage.data returns individual json with used and limit', function (): void {
    $subject = storagePanelSubject($this->user, '820000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($consultation, 'rnmc');
    storagePanelCertificate($consultation, 'comptroller');

    $response = $this->getJson(route('storage.data', ['view' => 'individual']));

    $response->assertOk()
        ->assertJsonPath('view', 'individual')
        ->assertJsonPath('used', 2)
        ->assertJsonPath('limit', config('certificates.storage_limit'))
        ->assertJsonCount(2, 'data');
});

it('destroyCertificate returns json with has_space true when needed fits after deletion', function (): void {
    config(['certificates.storage_limit' => 2]);

    $subject = storagePanelSubject($this->user, '830000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    $cert1 = storagePanelCertificate($consultation, 'rnmc');
    storagePanelCertificate($consultation, 'comptroller');

    $response = $this->deleteJson(route('storage.certificates.destroy', $cert1), ['needed' => 1]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'used' => 1,
            'limit' => 2,
            'has_space' => true,
        ]);
});

it('destroyCertificate returns json with has_space false when needed does not fit after deletion', function (): void {
    config(['certificates.storage_limit' => 2]);

    $subject = storagePanelSubject($this->user, '840000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    $cert1 = storagePanelCertificate($consultation, 'rnmc');
    storagePanelCertificate($consultation, 'comptroller');

    $response = $this->deleteJson(route('storage.certificates.destroy', $cert1), ['needed' => 2]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'used' => 1,
            'limit' => 2,
            'has_space' => false,
        ]);
});

it('destroyConsultation returns json with has_space true when needed fits after deletion', function (): void {
    config(['certificates.storage_limit' => 2]);

    $subject = storagePanelSubject($this->user, '850000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($consultation, 'rnmc');
    storagePanelCertificate($consultation, 'comptroller');

    $response = $this->deleteJson(route('storage.consultations.destroy', $consultation), ['needed' => 2]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'used' => 0,
            'limit' => 2,
            'has_space' => true,
        ]);
});

it('destroyConsultation returns json with has_space false when needed does not fit after deletion', function (): void {
    config(['certificates.storage_limit' => 2]);

    $subject = storagePanelSubject($this->user, '860000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($consultation, 'rnmc');
    storagePanelCertificate($consultation, 'comptroller');

    $otherSubject = storagePanelSubject($this->user, '860000002');
    $otherConsultation = storagePanelConsultation($this->user, $otherSubject);
    storagePanelCertificate($otherConsultation, 'rnmc');

    $response = $this->deleteJson(route('storage.consultations.destroy', $consultation), ['needed' => 2]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'used' => 1,
            'limit' => 2,
            'has_space' => false,
        ]);
});

it('destroyCertificatesBulk returns json with has_space true when needed fits after deletion', function (): void {
    config(['certificates.storage_limit' => 2]);

    $subject = storagePanelSubject($this->user, '870000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    $cert1 = storagePanelCertificate($consultation, 'rnmc');
    storagePanelCertificate($consultation, 'comptroller');

    $response = $this->deleteJson(route('storage.certificates.destroy-bulk'), [
        'ids' => [$cert1->id],
        'needed' => 1,
    ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'used' => 1,
            'limit' => 2,
            'has_space' => true,
        ]);
});

it('destroyCertificatesBulk returns json with has_space false when needed does not fit after deletion', function (): void {
    config(['certificates.storage_limit' => 2]);

    $subject = storagePanelSubject($this->user, '880000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    $cert1 = storagePanelCertificate($consultation, 'rnmc');
    storagePanelCertificate($consultation, 'comptroller');

    $response = $this->deleteJson(route('storage.certificates.destroy-bulk'), [
        'ids' => [$cert1->id],
        'needed' => 2,
    ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'used' => 1,
            'limit' => 2,
            'has_space' => false,
        ]);
});

it('destroyCertificatesBulk still redirects when json is not requested', function (): void {
    $subject = storagePanelSubject($this->user, '890000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    $cert = storagePanelCertificate($consultation, 'rnmc');

    $response = $this->delete(route('storage.certificates.destroy-bulk'), [
        'ids' => [$cert->id],
    ]);

    $response->assertRedirect(route('storage.index'));
});

it('grouped view excludes consultations without any stored pdf', function (): void {
    $subject = storagePanelSubject($this->user, '910000001');
    $freed = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($freed, 'rnmc', false);
    storagePanelCertificate($freed, 'comptroller', false);

    $keptSubject = storagePanelSubject($this->user, '910000002');
    $kept = storagePanelConsultation($this->user, $keptSubject);
    storagePanelCertificate($kept, 'rnmc');

    $response = $this->get(route('storage.index', ['view' => 'grouped']));

    $response->assertOk()
        ->assertDontSee('910000001')
        ->assertSee('910000002');
});

it('grouped view keeps incomplete consultations that still have at least one stored pdf', function (): void {
    $subject = storagePanelSubject($this->user, '920000001');
    $incomplete = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($incomplete, 'rnmc');
    storagePanelCertificate($incomplete, 'comptroller', false);

    $response = $this->get(route('storage.index', ['view' => 'grouped']));

    $response->assertOk()
        ->assertSee('920000001')
        ->assertSee('Incompleto');
});

it('storage.data grouped excludes consultations without any stored pdf', function (): void {
    $subject = storagePanelSubject($this->user, '930000001');
    $freed = storagePanelConsultation($this->user, $subject);
    storagePanelCertificate($freed, 'rnmc', false);
    storagePanelCertificate($freed, 'comptroller', false);

    $keptSubject = storagePanelSubject($this->user, '930000002');
    $kept = storagePanelConsultation($this->user, $keptSubject);
    storagePanelCertificate($kept, 'rnmc');

    $response = $this->getJson(route('storage.data', ['view' => 'grouped']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject.document_number', '930000002');
});

it('fully freed consultation still appears in the general consultation history', function (): void {
    $subject = storagePanelSubject($this->user, '940000001');
    $consultation = storagePanelConsultation($this->user, $subject);
    $cert1 = storagePanelCertificate($consultation, 'rnmc');
    $cert2 = storagePanelCertificate($consultation, 'comptroller');

    app(LawyerStorageService::class)->freeCertificates(new Collection([$cert1, $cert2]));

    expect($cert1->fresh()->pdf_path)->toBeNull()
        ->and($cert2->fresh()->pdf_path)->toBeNull();

    $response = $this->get(route('consultation-requests.index'));

    $response->assertOk()
        ->assertSee('940000001');
});
