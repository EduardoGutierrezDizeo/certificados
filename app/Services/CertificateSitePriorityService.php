<?php

namespace App\Services;

use App\Models\CertificateRequest;
use Illuminate\Support\Facades\Cache;

class CertificateSitePriorityService
{
    private const MIN_SAMPLES = 5;

    private const CACHE_TTL = 3600;

    private const CACHE_KEY = 'site_priority_durations';

    /**
     * Fallback durations (seconds) ordered from slowest to fastest.
     *
     * Ordered by observed complexity in the scrapers:
     * 1. attorney_general  — iframe navigation + verification question loop (up to 8 retries) + multi-strategy PDF download
     * 2. judicial_police   — reCAPTCHA v2 + terms acceptance step + extra navigation
     * 3. comptroller       — reCAPTCHA v2 + single download
     * 4. rnmc              — no CAPTCHA, no questions, just form → PDF
     */
    private const FALLBACK_DURATIONS = [
        'attorney_general' => 30,
        'judicial_police' => 18,
        'comptroller' => 15,
        'rnmc' => 7,
    ];

    public function getSitesSortedByDuration(array $sites): array
    {
        $durations = $this->getDurations();

        $ranked = [];

        foreach ($sites as $site) {
            $ranked[$site] = $durations[$site] ?? 0;
        }

        arsort($ranked);

        return array_keys($ranked);
    }

    /**
     * @return array<string, float>
     */
    public function getDurations(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return $this->calculateDurations();
        });
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, float>
     */
    private function calculateDurations(): array
    {
        $averages = CertificateRequest::where('status', 'success')
            ->whereNotNull('duration_seconds')
            ->select('site')
            ->selectRaw('COUNT(*) as sample_count')
            ->selectRaw('AVG(duration_seconds) as avg_duration')
            ->groupBy('site')
            ->get()
            ->keyBy('site');

        $durations = [];

        foreach (self::FALLBACK_DURATIONS as $site => $fallback) {
            $row = $averages->get($site);

            if ($row !== null && $row->sample_count >= self::MIN_SAMPLES) {
                $durations[$site] = (float) $row->avg_duration;
            } else {
                $durations[$site] = (float) $fallback;
            }
        }

        return $durations;
    }
}
