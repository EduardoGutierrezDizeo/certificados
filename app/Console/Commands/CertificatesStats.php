<?php

namespace App\Console\Commands;

use App\Models\CertificateRequest;
use Illuminate\Console\Command;

class CertificatesStats extends Command
{
    protected $signature = 'certificates:stats';

    protected $description = 'Muestra estadísticas de duración de generación de certificados por sitio';

    private const SITE_LABELS = [
        'rnmc' => 'RNMC',
        'judicial_police' => 'Policía Judicial',
        'comptroller' => 'Contraloría',
        'attorney_general' => 'Procuraduría',
    ];

    public function handle(): int
    {
        $sites = ['rnmc', 'judicial_police', 'comptroller', 'attorney_general'];

        $rows = [];

        foreach ($sites as $site) {
            $label = self::SITE_LABELS[$site] ?? $site;

            $stats = CertificateRequest::where('site', $site)
                ->where('status', 'success')
                ->whereNotNull('duration_seconds')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('ROUND(AVG(duration_seconds), 1) as avg')
                ->selectRaw('MIN(duration_seconds) as min')
                ->selectRaw('MAX(duration_seconds) as max')
                ->first();

            if ($stats->total === 0) {
                $rows[] = [$label, $stats->total, '—', '—', '—'];
            } else {
                $avg = number_format((float) $stats->avg, 1, '.', '').'s';
                $rows[] = [$label, $stats->total, $avg, $stats->min.'s', $stats->max.'s'];
            }
        }

        usort($rows, function ($a, $b) {
            $avgA = $a[2] === '—' ? -1 : (float) rtrim($a[2], 's');
            $avgB = $b[2] === '—' ? -1 : (float) rtrim($b[2], 's');

            return $avgB <=> $avgA;
        });

        $this->table(
            ['Sitio', 'Muestras', 'Promedio', 'Mín', 'Máx'],
            $rows
        );

        return self::SUCCESS;
    }
}
