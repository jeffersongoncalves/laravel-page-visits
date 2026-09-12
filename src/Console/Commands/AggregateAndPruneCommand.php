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

        $stats = [
            'visits_count' => $query()->where('is_bot', false)->count(),
            'unique_visits_count' => $query()->where('is_bot', false)->distinct()->count('ip_hash'),
            'bot_visits_count' => $query()->where('is_bot', true)->count(),
        ];

        foreach (self::DIMENSION_COLUMNS as $key => $column) {
            $stats[$key] = $this->dimensionCounts($query(), $column);
        }

        $stats['hourly_stats'] = $this->hourlyCounts($query());

        DB::table(config('page-visits.daily_stats_table', 'page_visit_daily_stats'))->updateOrInsert(
            ['date' => $date->toDateString()],
            [
                'visits_count' => $stats['visits_count'],
                'unique_visits_count' => $stats['unique_visits_count'],
                'bot_visits_count' => $stats['bot_visits_count'],
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

    protected function hourExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', visited_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM visited_at)::int',
            default => 'HOUR(visited_at)',
        };
    }
}
