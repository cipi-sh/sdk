<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * Metrics & health history (API 1.41+, Cipi 5.11+). Everything is synchronous:
 * the server reads its own SQLite database.
 */
final class Metrics extends Resource
{
    /**
     * The host now; per app the latest values, a 5-minute trend of the last
     * hour, uptime over 24h / 7d / 30d; the latest events; the collector state.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return $this->client->get('/metrics');
    }

    /**
     * A metric over time: about 240 points of [ts, avg, max] per metric.
     * Omit $app for the host. $range like "90m", "24h", "7d", "30d"; or $from / $to (Unix).
     *
     * @param  list<string>|string|null  $metrics
     * @return array<string, mixed>
     */
    public function series(?string $app = null, array|string|null $metrics = null, ?string $range = null, ?int $from = null, ?int $to = null): array
    {
        $query = $this->withoutNulls([
            'metrics' => is_array($metrics) ? implode(',', $metrics) : $metrics,
            'range' => $range,
            'from' => $from,
            'to' => $to,
        ]);
        if ($app !== null) {
            return $this->client->get($this->appPath($app).'/metrics', $query);
        }

        return $this->client->get('/metrics/series', $query);
    }

    /**
     * Healthcheck uptime: one app, or every app with a healthcheck.
     *
     * @return array<string, mixed>
     */
    public function uptime(?string $app = null, ?string $range = null): array
    {
        return $this->client->get('/metrics/uptime', $this->withoutNulls(['app' => $app, 'range' => $range]));
    }

    /**
     * Monitor state changes, health incidents, deploys, heartbeat — newest first.
     *
     * @param  'monitor'|'health'|'deploy'|'heartbeat'|null  $source
     * @return array<string, mixed>
     */
    public function events(?string $app = null, ?string $source = null, ?string $range = null, ?int $limit = null): array
    {
        return $this->client->get('/metrics/events', $this->withoutNulls([
            'app' => $app, 'source' => $source, 'range' => $range, 'limit' => $limit,
        ]));
    }

    /**
     * Collector, database, retention, probe, heartbeat (URL by host), OpenMetrics (no token).
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->client->get('/metrics/settings');
    }

    /**
     * Any of: enabled, probe, raw_days, rollup_days, resolution_minutes, events_days.
     *
     * @param  array<string, bool|int>  $settings
     * @return array<string, mixed>
     */
    public function updateSettings(array $settings): array
    {
        return $this->client->put('/metrics/settings', $settings);
    }

    /**
     * @return array<string, mixed>
     */
    public function retention(?int $rawDays = null, ?int $rollupDays = null, ?int $resolutionMinutes = null, ?int $eventsDays = null): array
    {
        return $this->updateSettings($this->withoutNulls([
            'raw_days' => $rawDays,
            'rollup_days' => $rollupDays,
            'resolution_minutes' => $resolutionMinutes,
            'events_days' => $eventsDays,
        ]));
    }

    /**
     * Call $url every $every minutes; when the server stops, the receiver alerts.
     *
     * @return array<string, mixed>
     */
    public function setHeartbeat(string $url, int $every = 1): array
    {
        return $this->client->put('/metrics/heartbeat', ['url' => $url, 'every' => $every]);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeHeartbeat(): array
    {
        return $this->client->delete('/metrics/heartbeat');
    }

    /**
     * @return array<string, mixed>
     */
    public function testHeartbeat(): array
    {
        return $this->client->post('/metrics/heartbeat/test');
    }
}
