<?php

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Models\User;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['must_change_password' => false, 'terms_version_accepted' => config('legal.terms_version')]);
    $this->user->assignRole('abogado');

    $this->subject = Subject::create([
        'lawyer_id' => $this->user->id,
        'document_type' => 'CC',
        'document_number' => '1234567890',
        'full_name' => 'Juan Perez',
    ]);
});

function makeConsultationFor(int $userId, int $subjectId): ConsultationRequest
{
    return ConsultationRequest::create([
        'lawyer_id' => $userId,
        'subject_id' => $subjectId,
        'status' => 'success',
    ]);
}

it('shows stats table with correct averages', function (): void {
    $c1 = makeConsultationFor($this->user->id, $this->subject->id);
    CertificateRequest::create([
        'consultation_request_id' => $c1->id,
        'site' => 'comptroller',
        'status' => 'success',
        'duration_seconds' => 40,
    ]);

    foreach ([10, 20, 30] as $duration) {
        $c = makeConsultationFor($this->user->id, $this->subject->id);
        CertificateRequest::create([
            'consultation_request_id' => $c->id,
            'site' => 'rnmc',
            'status' => 'success',
            'duration_seconds' => $duration,
        ]);
    }

    foreach ([5, 15] as $duration) {
        $c = makeConsultationFor($this->user->id, $this->subject->id);
        CertificateRequest::create([
            'consultation_request_id' => $c->id,
            'site' => 'judicial_police',
            'status' => 'success',
            'duration_seconds' => $duration,
        ]);
    }

    $this->artisan('certificates:stats')
        ->assertSuccessful()
        ->expectsTable(
            ['Sitio', 'Muestras', 'Promedio', 'Mín', 'Máx'],
            [
                ['Contraloría', 1, '40.0s', '40s', '40s'],
                ['RNMC', 3, '20.0s', '10s', '30s'],
                ['Policía Judicial', 2, '10.0s', '5s', '15s'],
                ['Procuraduría', 0, '—', '—', '—'],
            ]
        );
});

it('shows no-data sites when all sites are empty', function (): void {
    $this->artisan('certificates:stats')
        ->expectsTable(
            ['Sitio', 'Muestras', 'Promedio', 'Mín', 'Máx'],
            [
                ['RNMC', 0, '—', '—', '—'],
                ['Policía Judicial', 0, '—', '—', '—'],
                ['Contraloría', 0, '—', '—', '—'],
                ['Procuraduría', 0, '—', '—', '—'],
            ]
        );
});

it('ignores failed and null-duration records', function (): void {
    $c1 = makeConsultationFor($this->user->id, $this->subject->id);
    CertificateRequest::create([
        'consultation_request_id' => $c1->id,
        'site' => 'rnmc',
        'status' => 'failed',
        'duration_seconds' => 100,
    ]);

    $c2 = makeConsultationFor($this->user->id, $this->subject->id);
    CertificateRequest::create([
        'consultation_request_id' => $c2->id,
        'site' => 'rnmc',
        'status' => 'success',
        'duration_seconds' => null,
    ]);

    $this->artisan('certificates:stats')
        ->expectsTable(
            ['Sitio', 'Muestras', 'Promedio', 'Mín', 'Máx'],
            [
                ['RNMC', 0, '—', '—', '—'],
                ['Policía Judicial', 0, '—', '—', '—'],
                ['Contraloría', 0, '—', '—', '—'],
                ['Procuraduría', 0, '—', '—', '—'],
            ]
        );
});
