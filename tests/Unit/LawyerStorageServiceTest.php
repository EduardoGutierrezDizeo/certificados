<?php

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Models\User;
use App\Services\LawyerStorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshDatabaseWithRoles;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabaseWithRoles::class);

function makeStorageLawyer(): User
{
    $lawyer = User::factory()->create([
        'must_change_password' => false,
        'terms_version_accepted' => config('legal.terms_version'),
    ]);
    $lawyer->assignRole('abogado');

    return $lawyer;
}

function makeStorageSubject(User $lawyer): Subject
{
    return Subject::create([
        'lawyer_id' => $lawyer->id,
        'document_type' => 'CC',
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'full_name' => fake()->name(),
    ]);
}

function makeStorageConsultation(User $lawyer): ConsultationRequest
{
    return ConsultationRequest::create([
        'lawyer_id' => $lawyer->id,
        'subject_id' => makeStorageSubject($lawyer)->id,
        'status' => 'pending',
    ]);
}

function makeSuccessfulCertificate(User $lawyer, ?ConsultationRequest $consultation = null, ?string $pdfPath = null, ?string $pdfGeneratedAt = null): CertificateRequest
{
    $certificate = CertificateRequest::create([
        'consultation_request_id' => ($consultation ?? makeStorageConsultation($lawyer))->id,
        'site' => fake()->randomElement(['comptroller', 'judicial_police', 'rnmc', 'attorney_general']),
        'status' => 'success',
        'pdf_path' => $pdfPath ?? 'certificates/'.fake()->uuid().'.pdf',
        'pdf_generated_at' => $pdfGeneratedAt,
    ]);

    return $certificate;
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('usedCount counts only successful certificates belonging to the given lawyer', function (): void {
    $lawyerA = makeStorageLawyer();
    $lawyerB = makeStorageLawyer();

    makeSuccessfulCertificate($lawyerA);
    makeSuccessfulCertificate($lawyerA);

    makeSuccessfulCertificate($lawyerB);

    CertificateRequest::create([
        'consultation_request_id' => makeStorageConsultation($lawyerA)->id,
        'site' => 'comptroller',
        'status' => 'failed',
        'pdf_path' => null,
    ]);

    $service = new LawyerStorageService;

    expect($service->usedCount($lawyerA))->toBe(2)
        ->and($service->usedCount($lawyerB))->toBe(1);
});

it('usedCount ignores certificates without pdf_path', function (): void {
    $lawyer = makeStorageLawyer();

    makeSuccessfulCertificate($lawyer);

    CertificateRequest::create([
        'consultation_request_id' => makeStorageConsultation($lawyer)->id,
        'site' => 'comptroller',
        'status' => 'success',
        'pdf_path' => null,
    ]);

    $service = new LawyerStorageService;

    expect($service->usedCount($lawyer))->toBe(1);
});

it('hasSpaceFor returns false at the exact limit', function (): void {
    config(['certificates.storage_limit' => 500]);

    $lawyer = makeStorageLawyer();

    foreach (range(1, 500) as $ignored) {
        makeSuccessfulCertificate($lawyer);
    }

    $service = new LawyerStorageService;

    expect($service->usedCount($lawyer))->toBe(500)
        ->and($service->hasSpaceFor($lawyer, 1))->toBeFalse();
});

it('hasSpaceFor returns true just below the limit', function (): void {
    config(['certificates.storage_limit' => 500]);

    $lawyer = makeStorageLawyer();

    foreach (range(1, 499) as $ignored) {
        makeSuccessfulCertificate($lawyer);
    }

    $service = new LawyerStorageService;

    expect($service->usedCount($lawyer))->toBe(499)
        ->and($service->hasSpaceFor($lawyer, 1))->toBeTrue();
});

it('oldestCertificatesToFree orders by pdf_generated_at ascending', function (): void {
    $lawyer = makeStorageLawyer();

    $old = makeSuccessfulCertificate($lawyer, null, null, now()->subDays(3));
    $middle = makeSuccessfulCertificate($lawyer, null, null, now()->subDays(2));
    $new = makeSuccessfulCertificate($lawyer, null, null, now()->subDays(1));

    $service = new LawyerStorageService;

    $toFree = $service->oldestCertificatesToFree($lawyer, 2);

    expect($toFree->pluck('id')->all())->toBe([$old->id, $middle->id])
        ->and($toFree[0]->consultationRequest->relationLoaded('subject'))->toBeTrue();
});

it('oldestCertificatesToFree excludes certificates from the given consultation', function (): void {
    $lawyer = makeStorageLawyer();

    $consultation = makeStorageConsultation($lawyer);

    $oldOther = makeSuccessfulCertificate($lawyer, null, null, now()->subDays(3));
    $fromExcluded = makeSuccessfulCertificate($lawyer, $consultation, null, now()->subDays(2));
    $middleOther = makeSuccessfulCertificate($lawyer, null, null, now()->subDays(1));

    $service = new LawyerStorageService;

    $toFree = $service->oldestCertificatesToFree($lawyer, 3, $consultation->id);

    expect($toFree->pluck('id')->all())->toBe([$oldOther->id, $middleOther->id]);
});

it('freeCertificates deletes the file and resets pdf fields without changing status', function (): void {
    $lawyer = makeStorageLawyer();

    $consultation = makeStorageConsultation($lawyer);

    $certificate = makeSuccessfulCertificate($lawyer, $consultation, 'certificates/existing.pdf', now()->subDay());
    Storage::disk('local')->put('certificates/existing.pdf', 'dummy content');

    $service = new LawyerStorageService;

    $service->freeCertificates(new Collection([$certificate]));

    Storage::disk('local')->assertMissing('certificates/existing.pdf');

    $fresh = $certificate->fresh();

    expect($fresh->pdf_path)->toBeNull()
        ->and($fresh->pdf_generated_at)->toBeNull()
        ->and($fresh->status)->toBe('success');
});

it('freeCertificates ignores certificates whose file does not exist on disk', function (): void {
    $lawyer = makeStorageLawyer();

    $certificate = makeSuccessfulCertificate($lawyer, null, 'certificates/gone.pdf', now()->subDay());

    $service = new LawyerStorageService;

    $service->freeCertificates(new Collection([$certificate]));

    $fresh = $certificate->fresh();

    expect($fresh->pdf_path)->toBeNull()
        ->and($fresh->pdf_generated_at)->toBeNull()
        ->and($fresh->status)->toBe('success');
});
