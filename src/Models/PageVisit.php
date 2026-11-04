<?php

namespace JeffersonGoncalves\LaravelPageVisits\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property Carbon $visited_at
 * @property string $path
 * @property string|null $route_name
 * @property string $method
 * @property int|null $status_code
 * @property string|null $ip_hash
 * @property string|null $ip_anonymized
 * @property int|null $ip_version
 * @property string|null $device_type
 * @property string|null $browser
 * @property string|null $browser_version
 * @property string|null $operating_system
 * @property string|null $operating_system_version
 * @property string|null $country
 * @property string|null $country_code
 * @property string|null $region
 * @property string|null $city
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $timezone
 * @property string|null $isp
 * @property string|null $asn
 * @property string|null $referer_url
 * @property string|null $referer_host
 * @property string|null $referer_type
 * @property string|null $browser_language
 * @property string|null $locale
 * @property string|null $user_agent_hash
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property bool $is_bot
 * @property bool $is_vpn
 * @property bool $is_proxy
 * @property bool $is_tor
 * @property bool $is_datacenter
 * @property int|null $response_time_ms
 * @property Carbon|null $created_at
 */
class PageVisit extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'status_code' => 'integer',
            'ip_version' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_bot' => 'boolean',
            'is_vpn' => 'boolean',
            'is_proxy' => 'boolean',
            'is_tor' => 'boolean',
            'is_datacenter' => 'boolean',
            'response_time_ms' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('page-visits.table', 'page_visits');
    }

    /**
     * Crawler farms behind rotating residential proxies reuse a handful of
     * exact user agents across hundreds of IPs a day. Returns the
     * user_agent_hash values seen from more than farm_ua_ip_threshold
     * distinct ip_hash values in [$from, $to) (empty when the threshold is off).
     *
     * @return Collection<int, string>
     */
    public static function farmUserAgentHashes(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $threshold = config('page-visits.farm_ua_ip_threshold');

        if (! is_numeric($threshold) || (int) $threshold <= 0) {
            return collect();
        }

        return static::query()
            ->where('visited_at', '>=', $from)
            ->where('visited_at', '<', $to)
            ->whereNotNull('user_agent_hash')
            ->whereNotNull('ip_hash')
            ->groupBy('user_agent_hash')
            ->havingRaw('count(distinct ip_hash) > ?', [(int) $threshold])
            ->pluck('user_agent_hash');
    }

    /**
     * Drops rows whose user agent is a farm in [$from, $to). Rows with a
     * null user_agent_hash are kept.
     *
     * @param  Builder<PageVisit>  $query
     */
    public function scopeExcludingFarms(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $farm = static::farmUserAgentHashes($from, $to);

        // whereNotIn alone would drop null hashes (NULL NOT IN … is null).
        $query->when($farm->isNotEmpty(), fn (Builder $q) => $q->where(
            fn (Builder $q) => $q->whereNull('user_agent_hash')->orWhereNotIn('user_agent_hash', $farm)
        ));
    }
}
