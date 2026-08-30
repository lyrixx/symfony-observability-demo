<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provisions the Kibana and Redash dashboards. Run from inside the stack
 * (the "builder" container) via `castor app:provision`, so it can reach
 * Kibana/Redash by their plain Docker network hostnames: no /etc/hosts
 * entry, no router, no TLS to deal with, which the host-side castor task
 * this replaced needed and CI doesn't have.
 */
#[AsCommand(name: 'app:provision-dashboards', description: 'Provisions the Kibana and Redash dashboards')]
class ProvisionDashboardsCommand extends Command
{
    private const KIBANA_URL = 'http://kibana:5601';
    private const REDASH_URL = 'http://redash:5000';
    private const REDASH_EMAIL = 'admin@observability.test';
    private const REDASH_PASSWORD = 'observability';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Provisioning dashboards');

        // Right after "up", these two may still be booting: their compose
        // services have no healthcheck for castor's "up --wait" to wait on.
        $this->waitUntilReady(self::KIBANA_URL . '/api/status');
        $this->waitUntilReady(self::REDASH_URL . '/login');

        $this->provisionKibana($io);
        $this->provisionRedash($io);

        return Command::SUCCESS;
    }

    private function waitUntilReady(string $url, int $attempts = 30): void
    {
        for ($i = 1; $i <= $attempts; ++$i) {
            try {
                $this->request('GET', $url)->getStatusCode();

                return;
            } catch (\Throwable $e) {
                if ($i === $attempts) {
                    throw $e;
                }

                usleep(500_000);
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->httpClient->request($method, $url, [
            ...$options,
            'timeout' => 10,
        ]);
    }

    private function provisionKibana(SymfonyStyle $io): void
    {
        $io->section('Kibana: index pattern + visualizations + dashboard');

        $indexPatternRef = ['name' => 'kibanaSavedObjectMeta.searchSourceJSON.index', 'type' => 'index-pattern', 'id' => 'app-logs'];
        $searchSource = json_encode(['query' => ['query' => '', 'language' => 'kuery'], 'filter' => [], 'indexRefName' => $indexPatternRef['name']], JSON_THROW_ON_ERROR);

        $levelHistogram = $this->kibanaHistogramVisState('Log volume by level', 'level_name.keyword');
        $channelHistogram = $this->kibanaHistogramVisState('Log volume by channel', 'channel.keyword');
        $pieVisState = [
            'title' => 'Logs by level (total)',
            'type' => 'pie',
            'params' => [
                'type' => 'pie',
                'addTooltip' => true,
                'addLegend' => true,
                'legendPosition' => 'right',
                'isDonut' => true,
                'labels' => ['show' => false, 'values' => true, 'last_level' => true, 'truncate' => 100],
            ],
            'aggs' => [
                ['id' => '1', 'enabled' => true, 'type' => 'count', 'schema' => 'metric', 'params' => []],
                ['id' => '2', 'enabled' => true, 'type' => 'terms', 'schema' => 'segment', 'params' => ['field' => 'level_name.keyword', 'orderBy' => '1', 'order' => 'desc', 'size' => 8, 'otherBucket' => false, 'otherBucketLabel' => 'Other', 'missingBucket' => false, 'missingBucketLabel' => 'Missing']],
            ],
        ];

        $objects = [
            [
                'type' => 'index-pattern',
                'id' => 'app-logs',
                'attributes' => ['title' => 'app-*', 'timeFieldName' => 'datetime'],
            ],
            [
                'type' => 'search',
                'id' => 'app-recent-logs',
                'attributes' => [
                    'title' => 'Recent logs',
                    'columns' => ['datetime', 'level_name', 'channel', 'message'],
                    'sort' => [['datetime', 'desc']],
                    'kibanaSavedObjectMeta' => ['searchSourceJSON' => $searchSource],
                ],
                'references' => [$indexPatternRef],
            ],
            [
                'type' => 'visualization',
                'id' => 'app-log-volume',
                'attributes' => [
                    'title' => $levelHistogram['title'],
                    'visState' => json_encode($levelHistogram, JSON_THROW_ON_ERROR),
                    'uiStateJSON' => '{}',
                    'kibanaSavedObjectMeta' => ['searchSourceJSON' => $searchSource],
                ],
                'references' => [$indexPatternRef],
            ],
            [
                'type' => 'visualization',
                'id' => 'app-log-volume-by-channel',
                'attributes' => [
                    'title' => $channelHistogram['title'],
                    'visState' => json_encode($channelHistogram, JSON_THROW_ON_ERROR),
                    'uiStateJSON' => '{}',
                    'kibanaSavedObjectMeta' => ['searchSourceJSON' => $searchSource],
                ],
                'references' => [$indexPatternRef],
            ],
            [
                'type' => 'visualization',
                'id' => 'app-logs-by-level',
                'attributes' => [
                    'title' => $pieVisState['title'],
                    'visState' => json_encode($pieVisState, JSON_THROW_ON_ERROR),
                    'uiStateJSON' => '{}',
                    'kibanaSavedObjectMeta' => ['searchSourceJSON' => $searchSource],
                ],
                'references' => [$indexPatternRef],
            ],
            [
                'type' => 'dashboard',
                'id' => 'observability-overview',
                'attributes' => [
                    'title' => 'Observability overview',
                    'hits' => 0,
                    'description' => '',
                    'panelsJSON' => json_encode([
                        ['version' => '7.17.18', 'gridData' => ['x' => 0, 'y' => 0, 'w' => 48, 'h' => 15, 'i' => '1'], 'panelIndex' => '1', 'embeddableConfig' => [], 'panelRefName' => 'panel_1'],
                        ['version' => '7.17.18', 'gridData' => ['x' => 0, 'y' => 15, 'w' => 24, 'h' => 15, 'i' => '2'], 'panelIndex' => '2', 'embeddableConfig' => [], 'panelRefName' => 'panel_2'],
                        ['version' => '7.17.18', 'gridData' => ['x' => 24, 'y' => 15, 'w' => 24, 'h' => 15, 'i' => '3'], 'panelIndex' => '3', 'embeddableConfig' => [], 'panelRefName' => 'panel_3'],
                        ['version' => '7.17.18', 'gridData' => ['x' => 0, 'y' => 30, 'w' => 48, 'h' => 15, 'i' => '4'], 'panelIndex' => '4', 'embeddableConfig' => ['columns' => ['datetime', 'level_name', 'channel', 'message']], 'panelRefName' => 'panel_4'],
                    ], JSON_THROW_ON_ERROR),
                    'optionsJSON' => json_encode(['useMargins' => true, 'syncColors' => false, 'hidePanelTitles' => false], JSON_THROW_ON_ERROR),
                    'timeRestore' => true,
                    'timeTo' => 'now',
                    'timeFrom' => 'now-6h',
                    'refreshInterval' => ['pause' => false, 'value' => 30000],
                    'kibanaSavedObjectMeta' => ['searchSourceJSON' => json_encode(['query' => ['query' => '', 'language' => 'kuery'], 'filter' => []], JSON_THROW_ON_ERROR)],
                ],
                'references' => [
                    ['name' => 'panel_1', 'type' => 'visualization', 'id' => 'app-log-volume'],
                    ['name' => 'panel_2', 'type' => 'visualization', 'id' => 'app-log-volume-by-channel'],
                    ['name' => 'panel_3', 'type' => 'visualization', 'id' => 'app-logs-by-level'],
                    ['name' => 'panel_4', 'type' => 'search', 'id' => 'app-recent-logs'],
                ],
            ],
        ];

        $this->request('POST', self::KIBANA_URL . '/api/saved_objects/_bulk_create?overwrite=true', [
            'headers' => ['kbn-xsrf' => 'true'],
            'json' => $objects,
        ]);

        $io->comment('Kibana: dashboard "Observability overview" is up to date.');
    }

    /**
     * @return array<string, mixed>
     */
    private function kibanaHistogramVisState(string $title, string $termsField): array
    {
        return [
            'title' => $title,
            'type' => 'histogram',
            'params' => [
                'type' => 'histogram',
                'grid' => ['categoryLines' => false],
                'categoryAxes' => [['id' => 'CategoryAxis-1', 'type' => 'category', 'position' => 'bottom', 'show' => true, 'style' => [], 'scale' => ['type' => 'linear'], 'labels' => ['show' => true, 'truncate' => 100], 'title' => []]],
                'valueAxes' => [['id' => 'ValueAxis-1', 'name' => 'LeftAxis-1', 'type' => 'value', 'position' => 'left', 'show' => true, 'style' => [], 'scale' => ['type' => 'linear', 'mode' => 'normal'], 'labels' => ['show' => true, 'rotate' => 0, 'filter' => false, 'truncate' => 100], 'title' => ['text' => 'Count']]],
                'seriesParams' => [['show' => true, 'type' => 'histogram', 'mode' => 'stacked', 'data' => ['label' => 'Count', 'id' => '1'], 'valueAxis' => 'ValueAxis-1', 'drawLinesBetweenPoints' => true, 'showCirclesOnLines' => true, 'interpolate' => 'linear', 'lineWidth' => 2, 'showCircles' => true]],
                'addTooltip' => true,
                'addLegend' => true,
                'legendPosition' => 'right',
                'times' => [],
                'addTimeMarker' => false,
                'labels' => [],
                'thresholdLine' => ['show' => false, 'value' => 10, 'width' => 1, 'style' => 'full', 'color' => '#E7664C'],
            ],
            'aggs' => [
                ['id' => '1', 'enabled' => true, 'type' => 'count', 'schema' => 'metric', 'params' => []],
                ['id' => '2', 'enabled' => true, 'type' => 'date_histogram', 'schema' => 'segment', 'params' => ['field' => 'datetime', 'timeRange' => ['from' => 'now-6h', 'to' => 'now'], 'useNormalizedEsInterval' => true, 'scaleMetricValues' => false, 'interval' => 'auto', 'drop_partials' => false, 'min_doc_count' => 1, 'extended_bounds' => []]],
                ['id' => '3', 'enabled' => true, 'type' => 'terms', 'schema' => 'group', 'params' => ['field' => $termsField, 'orderBy' => '1', 'order' => 'desc', 'size' => 8, 'otherBucket' => false, 'otherBucketLabel' => 'Other', 'missingBucket' => false, 'missingBucketLabel' => 'Missing']],
            ],
        ];
    }

    private function provisionRedash(SymfonyStyle $io): void
    {
        $io->section('Redash: ClickHouse data source + queries + dashboard');

        $cookie = $this->redashLogin() ?? $this->redashSetup();

        $dataSourceId = $this->redashFindOrCreate($cookie, '/api/data_sources', 'ClickHouse', [
            'name' => 'ClickHouse',
            'type' => 'clickhouse',
            'options' => ['url' => 'http://clickhouse:8123', 'user' => 'app', 'password' => 'app', 'dbname' => 'app'],
        ]);

        $volumeQueryId = $this->redashFindOrCreateQuery($cookie, 'Log volume over time', $dataSourceId,
            'SELECT toStartOfInterval(datetime, INTERVAL 5 minute) AS bucket, count() AS total FROM app.logs GROUP BY bucket ORDER BY bucket'
        );
        $volumeVizId = $this->redashFindOrCreateVisualization($cookie, $volumeQueryId, 'Log volume over time', 'CHART', [
            'globalSeriesType' => 'line',
            'sortX' => true,
            'xAxis' => ['type' => 'datetime'],
            'columnMapping' => ['bucket' => 'x', 'total' => 'y'],
            'seriesOptions' => ['total' => ['type' => 'line']],
        ]);

        $levelQueryId = $this->redashFindOrCreateQuery($cookie, 'Logs by level', $dataSourceId,
            'SELECT level_name, count() AS total FROM app.logs GROUP BY level_name ORDER BY total DESC'
        );
        $levelVizId = $this->redashFindOrCreateVisualization($cookie, $levelQueryId, 'Logs by level', 'CHART', [
            'globalSeriesType' => 'column',
            'sortX' => true,
            'columnMapping' => ['level_name' => 'x', 'total' => 'y'],
            'seriesOptions' => ['total' => ['type' => 'column']],
        ]);

        $channelQueryId = $this->redashFindOrCreateQuery($cookie, 'Logs by channel', $dataSourceId,
            'SELECT channel, count() AS total FROM app.logs GROUP BY channel ORDER BY total DESC'
        );
        $channelVizId = $this->redashFindOrCreateVisualization($cookie, $channelQueryId, 'Logs by channel', 'CHART', [
            'globalSeriesType' => 'column',
            'sortX' => true,
            'columnMapping' => ['channel' => 'x', 'total' => 'y'],
            'seriesOptions' => ['total' => ['type' => 'column']],
        ]);

        $recentQueryId = $this->redashFindOrCreateQuery($cookie, 'Recent logs', $dataSourceId,
            'SELECT datetime, level_name, channel, message FROM app.logs ORDER BY datetime DESC LIMIT 50'
        );
        $recentVizId = $this->request('GET', self::REDASH_URL . "/api/queries/{$recentQueryId}", ['headers' => ['Cookie' => $cookie]])
            ->toArray()['visualizations'][0]['id']
        ;

        $dashboardName = 'Observability Overview';
        $dashboardId = $this->redashFindOrCreate($cookie, '/api/dashboards', $dashboardName, [
            'name' => $dashboardName,
        ]);
        // Redash's single-dashboard endpoints (unlike the collection ones) are
        // addressed by slug, not by id.
        $dashboardSlug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $dashboardName), '-'));

        $this->redashEnsureWidget($cookie, $dashboardId, $dashboardSlug, $volumeVizId, ['col' => 0, 'row' => 0, 'sizeX' => 6, 'sizeY' => 8]);
        $this->redashEnsureWidget($cookie, $dashboardId, $dashboardSlug, $levelVizId, ['col' => 0, 'row' => 8, 'sizeX' => 3, 'sizeY' => 8]);
        $this->redashEnsureWidget($cookie, $dashboardId, $dashboardSlug, $channelVizId, ['col' => 3, 'row' => 8, 'sizeX' => 3, 'sizeY' => 8]);
        $this->redashEnsureWidget($cookie, $dashboardId, $dashboardSlug, $recentVizId, ['col' => 0, 'row' => 16, 'sizeX' => 6, 'sizeY' => 8]);

        $this->request('POST', self::REDASH_URL . "/api/dashboards/{$dashboardId}", [
            'headers' => ['Cookie' => $cookie],
            'json' => ['is_draft' => false],
        ]);

        $io->comment('Redash: dashboard "Observability Overview" is up to date.');
        $io->note(\sprintf('Redash admin login: %s / %s', self::REDASH_EMAIL, self::REDASH_PASSWORD));
    }

    private function redashLogin(): ?string
    {
        $response = $this->request('POST', self::REDASH_URL . '/login', [
            'max_redirects' => 0,
            'body' => ['email' => self::REDASH_EMAIL, 'password' => self::REDASH_PASSWORD],
        ]);

        return 302 === $response->getStatusCode() ? $this->redashExtractCookie($response) : null;
    }

    private function redashSetup(): string
    {
        $response = $this->request('POST', self::REDASH_URL . '/setup', [
            'max_redirects' => 0,
            'body' => [
                'name' => 'Observability Admin',
                'email' => self::REDASH_EMAIL,
                'password' => self::REDASH_PASSWORD,
                'org_name' => 'Observability',
            ],
        ]);

        return $this->redashExtractCookie($response) ?? throw new \RuntimeException('Could not complete the Redash setup wizard.');
    }

    private function redashExtractCookie(ResponseInterface $response): ?string
    {
        foreach ($response->getHeaders(false)['set-cookie'] ?? [] as $setCookie) {
            if (str_starts_with($setCookie, 'session=')) {
                return explode(';', $setCookie, 2)[0];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function redashFindOrCreate(string $cookie, string $collectionPath, string $name, array $payload): int
    {
        $existing = $this->request('GET', self::REDASH_URL . $collectionPath, ['headers' => ['Cookie' => $cookie]])->toArray();
        foreach ($existing['results'] ?? $existing as $item) {
            if (($item['name'] ?? null) === $name) {
                return $item['id'];
            }
        }

        return $this->request('POST', self::REDASH_URL . $collectionPath, ['headers' => ['Cookie' => $cookie], 'json' => $payload])
            ->toArray()['id']
        ;
    }

    private function redashFindOrCreateQuery(string $cookie, string $name, int $dataSourceId, string $sql): int
    {
        return $this->redashFindOrCreate($cookie, '/api/queries', $name, [
            'name' => $name,
            'data_source_id' => $dataSourceId,
            'query' => $sql,
            'options' => new \stdClass(),
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function redashFindOrCreateVisualization(string $cookie, int $queryId, string $name, string $type, array $options): int
    {
        $query = $this->request('GET', self::REDASH_URL . "/api/queries/{$queryId}", ['headers' => ['Cookie' => $cookie]])->toArray();
        foreach ($query['visualizations'] ?? [] as $visualization) {
            if ($visualization['name'] === $name) {
                return $visualization['id'];
            }
        }

        return $this->request('POST', self::REDASH_URL . '/api/visualizations', [
            'headers' => ['Cookie' => $cookie],
            'json' => ['query_id' => $queryId, 'type' => $type, 'name' => $name, 'options' => $options],
        ])->toArray()['id'];
    }

    /**
     * @param array{col: int, row: int, sizeX: int, sizeY: int} $position
     */
    private function redashEnsureWidget(string $cookie, int $dashboardId, string $dashboardSlug, int $visualizationId, array $position): void
    {
        $dashboard = $this->request('GET', self::REDASH_URL . "/api/dashboards/{$dashboardSlug}", ['headers' => ['Cookie' => $cookie]])->toArray();
        foreach ($dashboard['widgets'] ?? [] as $widget) {
            if (($widget['visualization']['id'] ?? null) === $visualizationId) {
                return;
            }
        }

        $this->request('POST', self::REDASH_URL . '/api/widgets', [
            'headers' => ['Cookie' => $cookie],
            'json' => [
                'dashboard_id' => $dashboardId,
                'visualization_id' => $visualizationId,
                'text' => '',
                'width' => 1,
                'options' => ['position' => $position],
            ],
        ]);
    }
}
