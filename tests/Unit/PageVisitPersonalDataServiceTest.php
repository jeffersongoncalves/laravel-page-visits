<?php

use JeffersonGoncalves\LaravelPageVisits\Compliance\PageVisitPersonalDataService;
use JeffersonGoncalves\LaravelPageVisits\Models\PageVisit;
use JeffersonGoncalves\VisitorFingerprint\Support\IpAnonymizer;

it('exports and forgets rows matching a hashed ip', function () {
    $ip = '203.0.113.55';

    PageVisit::query()->create([
        'visited_at' => now(),
        'path' => 'home',
        'method' => 'GET',
        'ip_hash' => IpAnonymizer::hash($ip),
        'ip_anonymized' => IpAnonymizer::truncate($ip),
        'ip_version' => IpAnonymizer::version($ip),
        'user_agent_hash' => hash('sha256', 'ua'),
        'is_bot' => false,
        'is_vpn' => false,
        'is_proxy' => false,
        'is_tor' => false,
        'is_datacenter' => false,
    ]);

    $service = new PageVisitPersonalDataService;

    expect($service->exportForIp($ip))->toHaveCount(1);

    $forgotten = $service->forgetForIp($ip);

    expect($forgotten)->toBe(1);

    $visit = PageVisit::query()->first();

    expect($visit->ip_hash)->toBeNull()
        ->and($visit->ip_anonymized)->toBeNull()
        ->and($visit->user_agent_hash)->toBeNull();
});
