<?php

namespace JeffersonGoncalves\LaravelPageVisits\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JeffersonGoncalves\LaravelPageVisits\Models\PageVisit;

/**
 * Folds a day's raw visits into page_visit_daily_stats, then prunes visit
 * rows past the configured retention window. Defaults to aggregating
 * yesterday (today is still live, queryable straight off page_visits).
 */
class AggregateAndPruneCommand extends Command
{
    /**
     * @var array<string, string>
     */
    protected const DIMENSION_COLUMNS = [
        'device_stats' => 'device_type',
        'browser_stats' => 'browser',
        'os_stats' => 'operating_system',
        'country_stats' => 'country_code',
        'city_stats' => 'city',
        'referer_stats' => 'referer_host',
        'referer_type_stats' => 'referer_type',
        'utm_source_stats' => 'utm_source',
        'utm_medium_stats' => 'utm_medium',
        'utm_campaign_stats' => 'utm_campaign',
        'language_stats' => 'browser_language',
    ];

    protected $signature = 'page-visits:aggregate-and-prune {--date= : Date to aggregate (Y-m-d), defaults to yesterday}';

    protected $description = 'Aggregate visits into page_visit_daily_stats and prune expired visit rows.';

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse((string) $this->option('date')) : Carbon::yesterday();

        $this->aggregate($date);
        $this->prune();

        return self::SUCCESS;
    }

    protected function aggregate(Carbon $date): void
    {
        $from = $date->copy()->startOfDay();
        $to = $date->copy()->endOfDay();

        $query = fn (): Builder => PageVisit::query()->where('visited_at', '>=', $from)->where('visited_at', '<=', $to);

        $humans = fn (): Builder => $query()->where('is_bot', false)->where('is_datacenter', false);

        $stats = [
            'visits_count' => $humans()->count(),
            'unique_visits_count' => $humans()->distinct()->count('ip_hash'),
            'bot_visits_count' => $query()->where('is_bot', true)->count(),
            'datacenter_visits_count' => $query()->where('is_bot', false)->where('is_datacenter', true)->count(),
        ];

        foreach (self::DIMENSION_COLUMNS as $key => $column) {
            $stats[$key] = $this->dimensionCounts($query(), $column);
        }

        $stats['hourly_stats'] = $this->hourlyCounts($query());
        $stats['response_time_stats'] = $this->responseTimeStats($query());

        DB::table(config('page-visits.daily_stats_table', 'page_visit_daily_stats'))->updateOrInsert(
            ['date' => $date->toDateString()],
            [
                'visits_count' => $stats['visits_count'],
                'unique_visits_count' => $stats['unique_visits_count'],
                'bot_visits_count' => $stats['bot_visits_count'],
                'datacenter_visits_count' => $stats['datacenter_visits_count'],
                'device_stats' => json_encode($stats['device_stats']),
                'browser_stats' => json_encode($stats['browser_stats']),
                'os_stats' => json_encode($stats['os_stats']),
                'country_stats' => json_encode($stats['country_stats']),
                'city_stats' => json_encode($stats['city_stats']),
                'referer_stats' => json_encode($stats['referer_stats']),
                'referer_type_stats' => json_encode($stats['referer_type_stats']),
                'utm_source_stats' => json_encode($stats['utm_source_stats']),
                'utm_medium_stats' => json_encode($stats['utm_medium_stats']),
                'utm_campaign_stats' => json_encode($stats['utm_campaign_stats']),
                'language_stats' => json_encode($stats['language_stats']),
                'hourly_stats' => json_encode($stats['hourly_stats']),
                'response_time_stats' => $stats['response_time_stats'] === null ? null : json_encode($stats['response_time_stats']),
                'updated_at' => now(),
            ]
        );
    }

    protected function prune(): void
    {
        PageVisit::query()
            ->where('visited_at', '<', now()->subDays((int) config('page-visits.retention_days', 400)))
            ->delete();
    }

    /**
     * @param  Builder<PageVisit>  $query
     * @return array<string, int>
     */
    protected function dimensionCounts(Builder $query, string $column): array
    {
        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->selectRaw("{$column} as label, count(*) as aggregate")
            ->pluck('aggregate', 'label')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  Builder<PageVisit>  $query
     * @return array<int, int>
     */
    protected function hourlyCounts(Builder $query): array
    {
        $hour = $this->hourExpression();

        return $query
            ->selectRaw("{$hour} as hour, count(*) as aggregate")
            ->groupBy(DB::raw($hour))
            ->pluck('aggregate', 'hour')
            ->mapWithKeys(fn ($count, $hour) => [(int) $hour => (int) $count])
            ->all();
    }

    /**
     * Nearest-rank percentiles over the day's response times (bots included:
     * this measures server load, not audience).
     *
     * @param  Builder<PageVisit>  $query
     * @return array{count: int, avg: int, min: int, max: int, p50: int, p95: int, p99: int}|null
     */
    protected function responseTimeStats(Builder $query): ?array
    {
        $query->whereNotNull('response_time_ms');

        $totals = $query->clone()
            ->toBase()
            ->selectRaw('count(*) as total, avg(response_time_ms) as average, min(response_time_ms) as fastest, max(response_time_ms) as slowest')
            ->first();

        $count = (int) $totals->total;

        if ($count === 0) {
            return null;
        }

        // One indexed-offset query per percentile keeps memory flat however
        // many visits the day holds.
        $percentile = fn (int $p): int => (int) $query->clone()
            ->orderBy('response_time_ms')
            ->offset(max(0, (int) ceil($p / 100 * $count) - 1))
            ->limit(1)
            ->value('response_time_ms');

        return [
            'count' => $count,
            'avg' => (int) round((float) $totals->average),
            'min' => (int) $totals->fastest,
            'max' => (int) $totals->slowest,
            'p50' => $percentile(50),
            'p95' => $percentile(95),
            'p99' => $percentile(99),
        ];
    }

    protected function hourExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', visited_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM visited_at)::int',
            default => 'HOUR(visited_at)',
        };
    }
}
