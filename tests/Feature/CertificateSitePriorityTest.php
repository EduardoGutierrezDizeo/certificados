<?php

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Models\User;
use App\Services\CertificateJobDispatcher;
use App\Services\CertificateSitePriorityService;
use Illuminate\Support\Facades\Redis;
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

function makeConsultation(int $userId, int $subjectId): ConsultationRequest
{
    return ConsultationRequest::create([
        'lawyer_id' => $userId,
        'subject_id' => $subjectId,
        'status' => 'success',
    ]);
}

function createCert(string $site, int $duration, int $consultationId): CertificateRequest
{
    return CertificateRequest::create([
        'consultation_request_id' => $consultationId,
        'site' => $site,
        'status' => 'success',
        'duration_seconds' => $duration,
    ]);
}

it('uses fallback durations when no historical data exists', function (): void {
    $service = new CertificateSitePriorityService;
    $durations = $service->getDurations();

    expect($durations)->toBe([
        'attorney_general' => 30.0,
        'judicial_police' => 18.0,
        'comptroller' => 15.0,
        'rnmc' => 7.0,
    ]);
});

it('uses real averages when enough samples exist', function (): void {
    foreach (range(1, 5) as $i) {
        $c = makeConsultation($this->user->id, $this->subject->id);
        createCert('rnmc', 5, $c->id);
    }

    $service = new CertificateSitePriorityService;
    $durations = $service->getDurations();

    expect($durations['rnmc'])->toBe(5.0);
    expect($durations['attorney_general'])->toBe(30.0);
});

it('falls back when samples are below threshold', function (): void {
    foreach (range(1, 3) as $i) {
        $c = makeConsultation($this->user->id, $this->subject->id);
        createCert('comptroller', 25, $c->id);
    }

    $service = new CertificateSitePriorityService;
    $durations = $service->getDurations();

    expect($durations['comptroller'])->toBe(15.0);
});

it('mixes real averages and fallbacks correctly', function (): void {
    foreach (range(1, 5) as $i) {
        $c = makeConsultation($this->user->id, $this->subject->id);
        createCert('comptroller', 20, $c->id);
    }

    foreach (range(1, 10) as $i) {
        $c = makeConsultation($this->user->id, $this->subject->id);
        createCert('rnmc', 4, $c->id);
    }

    $service = new CertificateSitePriorityService;
    $durations = $service->getDurations();

    expect($durations['comptroller'])->toBe(20.0);
    expect($durations['rnmc'])->toBe(4.0);
    expect($durations['attorney_general'])->toBe(30.0);
    expect($durations['judicial_police'])->toBe(18.0);
});

it('sorts sites from slowest to fastest', function (): void {
    $service = new CertificateSitePriorityService;
    $sorted = $service->getSitesSortedByDuration(['rnmc', 'comptroller', 'judicial_police', 'attorney_general']);

    expect($sorted)->toBe(['attorney_general', 'judicial_police', 'comptroller', 'rnmc']);
});

it('sorts sites using real averages when available', function (): void {
    foreach (range(1, 5) as $i) {
        $c = makeConsultation($this->user->id, $this->subject->id);
        createCert('rnmc', 40, $c->id);
    }

    $service = new CertificateSitePriorityService;
    $sorted = $service->getSitesSortedByDuration(['rnmc', 'comptroller', 'judicial_police', 'attorney_general']);

    expect($sorted[0])->toBe('rnmc');
});

it('dispatches jobs in slowest-first order', function (): void {
    $certificateRequests = collect();

    foreach (['rnmc', 'comptroller', 'judicial_police', 'attorney_general'] as $site) {
        $c = makeConsultation($this->user->id, $this->subject->id);
        $certificateRequests->push(
            CertificateRequest::create([
                'consultation_request_id' => $c->id,
                'site' => $site,
                'status' => 'pending',
            ])
        );
    }

    $pushedSites = [];
    Redis::shouldReceive('rpush')->andReturnUsing(function ($key, $value) use (&$pushedSites) {
        $payload = json_decode($value, true);
        $pushedSites[] = $payload['site'];
    })->times(4);

    $dispatcher = app(CertificateJobDispatcher::class);
    $dispatcher->dispatchMultiple($certificateRequests);

    expect($pushedSites)->toBe([
        'attorney_general',
        'judicial_police',
        'comptroller',
        'rnmc',
    ]);
});

it('dispatches in fallback order when no historical data', function (): void {
    $certificateRequests = collect();

    foreach (['rnmc', 'comptroller', 'judicial_police', 'attorney_general'] as $site) {
        $c = makeConsultation($this->user->id, $this->subject->id);
        $certificateRequests->push(
            CertificateRequest::create([
                'consultation_request_id' => $c->id,
                'site' => $site,
                'status' => 'pending',
            ])
        );
    }

    $pushedSites = [];
    Redis::shouldReceive('rpush')->andReturnUsing(function ($key, $value) use (&$pushedSites) {
        $payload = json_decode($value, true);
        $pushedSites[] = $payload['site'];
    })->times(4);

    $dispatcher = app(CertificateJobDispatcher::class);
    $dispatcher->dispatchMultiple($certificateRequests);

    expect($pushedSites)->toBe([
        'attorney_general',
        'judicial_police',
        'comptroller',
        'rnmc',
    ]);
});

it('updates status to processing for each dispatched request', function (): void {
    $certificateRequests = collect();

    foreach (['rnmc', 'comptroller'] as $site) {
        $c = makeConsultation($this->user->id, $this->subject->id);
        $certificateRequests->push(
            CertificateRequest::create([
                'consultation_request_id' => $c->id,
                'site' => $site,
                'status' => 'pending',
            ])
        );
    }

    Redis::shouldReceive('rpush')->times(2);

    $dispatcher = app(CertificateJobDispatcher::class);
    $dispatcher->dispatchMultiple($certificateRequests);

    foreach ($certificateRequests as $cr) {
        expect($cr->fresh()->status)->toBe('processing');
    }
});
