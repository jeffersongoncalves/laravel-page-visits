<?php

namespace JeffersonGoncalves\LaravelPageVisits\Compliance;

use JeffersonGoncalves\LaravelPageVisits\Models\PageVisit;
use JeffersonGoncalves\VisitorFingerprint\Compliance\PersonalDataExporter;

/**
 * Thin LGPD/GDPR wrapper around the visitor-fingerprint package's generic
 * exporter, scoped to this package's own PageVisit model.
 */
class PageVisitPersonalDataService
{
    protected PersonalDataExporter $exporter;

    public function __construct()
    {
        $this->exporter = new PersonalDataExporter(PageVisit::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exportForIp(string $ip): array
    {
        return $this->exporter->exportForIp($ip);
    }

    public function forgetForIp(string $ip): int
    {
        return $this->exporter->forgetForIp($ip);
    }
}
