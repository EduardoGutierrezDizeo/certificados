<?php

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

dataset('certificate storage disks', [
    'local' => ['local'],
    's3' => ['s3'],
]);

it('runs the full certificate flow on the {disk} disk', function (string $disk): void {
    config(['filesystems.default' => $disk]);
    config(['services.internal_api.key' => 'test-internal-key-12345']);

    Storage::fake($disk);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_session_id' => null,
        'must_change_password' => false,
        'terms_version_accepted' => config('legal.terms_version'),
    ]);
    $user->assignRole('abogado');

    $plan = SubscriptionPlan::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $subject = Subject::create([
        'lawyer_id' => $user->id,
        'document_type' => 'CC',
        'document_number' => '1010101010',
        'full_name' => 'Persona PDF',
    ]);

    $consultation = ConsultationRequest::create([
        'lawyer_id' => $user->id,
        'subject_id' => $subject->id,
        'status' => 'pending',
    ]);

    $cert = CertificateRequest::create([
        'consultation_request_id' => $consultation->id,
        'site' => 'rnmc',
        'status' => 'pending',
    ]);

    $pdf = UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf');
    $expectedContents = $pdf->getContent();

    $this->postJson(
        "/api/internal/certificate-requests/{$cert->id}/complete",
        [
            'status' => 'success',
            'duration_seconds' => 15,
            'pdf' => $pdf,
        ],
        ['X-Internal-Api-Key' => 'test-internal-key-12345']
    )->assertOk();

    $cert->refresh();
    $pdfPath = $cert->pdf_path;

    expect($cert->status)->toBe('success');
    expect($pdfPath)->not->toBeNull();

    Storage::disk($disk)->assertExists($pdfPath);
    expect(Storage::disk($disk)->get($pdfPath))->toBe($expectedContents);

    $this->actingAs($user);

    $data = $this->getJson(route('storage.data', ['view' => 'individual']));

    $data->assertOk()
        ->assertJsonPath('used', 1)
        ->assertJsonCount(1, 'data');

    expect((int) $data->json('data.0.size_bytes'))->toBe(strlen($expectedContents));

    $download = $this->get(route('certificate-requests.download', $cert));

    $download->assertOk()
        ->assertDownload();

    expect($download->streamedContent())->toBe($expectedContents);

    $zip = $this->get(route('consultation-requests.download-zip', $consultation));

    $zip->assertOk()
        ->assertDownload("{$subject->document_number}.zip");

    $zipPath = $zip->baseResponse->getFile()->getPathname();

    $archive = new ZipArchive;
    expect($archive->open($zipPath))->toBeTrue();
    expect($archive->numFiles)->toBe(1)
        ->and($archive->getNameIndex(0))->toBe('RNMC.pdf');

    $zipContents = $archive->getFromIndex(0);
    $archive->close();

    expect($zipContents)->toBe($expectedContents);

    $zip->streamedContent();

    $this->delete(route('storage.certificates.destroy', $cert))
        ->assertRedirect(route('storage.index'));

    expect($cert->fresh()->pdf_path)->toBeNull()
        ->and($cert->fresh()->status)->toBe('success');

    Storage::disk($disk)->assertMissing($pdfPath);
})->with('certificate storage disks');
