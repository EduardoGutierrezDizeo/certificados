<?php

use Illuminate\Support\Facades\Redis;

it('shows zero pending jobs when queue is empty', function (): void {
    Redis::shouldReceive('llen')->with('certificate_jobs')->once()->andReturn(0);

    $this->artisan('certificates:queue-status')
        ->assertExitCode(0);

    Redis::shouldHaveReceived('llen')->with('certificate_jobs');
});

it('shows the pending job count when queue has jobs', function (): void {
    Redis::shouldReceive('llen')->with('certificate_jobs')->once()->andReturn(7);

    $this->artisan('certificates:queue-status')
        ->expectsOutput('Jobs pendientes en \'certificate_jobs\': 7')
        ->expectsOutputToContain('Hay 7 certificado(s) esperando')
        ->assertExitCode(0);

    Redis::shouldHaveReceived('llen')->with('certificate_jobs');
});
