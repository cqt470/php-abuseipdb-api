<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Unit;

    use PHPUnit\Framework\TestCase;
    use Zexa\Includes\ReportSummary;

    final class ReportSummaryTest extends TestCase
    {
        private const NOW = 1795000000; // fixed reference time

        private static function report(int $ago, array $categories, int|string|null $reporter, ?string $country): array
        {
            return [
                'reportedAt'          => gmdate('Y-m-d\TH:i:sP', self::NOW - $ago),
                'comment'             => 'x',
                'categories'          => $categories,
                'reporterId'          => $reporter,
                'reporterCountryCode' => $country,
                'reporterCountryName' => $country,
            ];
        }

        public function test_summarizes_categories_timeline_countries_and_reporters(): void
        {
            $reports = [
                self::report(3600, [22, 18], 1, 'DE'),
                self::report(7200, [22], 2, 'US'),
                self::report(3 * 86400, [22, 18, 18], 1, 'DE'), // 18 twice counts once
                self::report(20 * 86400, [14], 3, 'IT'),
            ];

            $summary = ReportSummary::summarize($reports, self::NOW);

            $this->assertSame([
                'sampleSize' => 4,
                'categories' => [
                    ['id' => 22, 'name' => 'SSH', 'count' => 3, 'percent' => 75],
                    ['id' => 18, 'name' => 'Brute-Force', 'count' => 2, 'percent' => 50],
                    ['id' => 14, 'name' => 'Port Scan', 'count' => 1, 'percent' => 25],
                ],
                'topCategory' => ['id' => 22, 'name' => 'SSH', 'percent' => 75],
                'timeline' => [
                    'last24h' => 2,
                    'last7d'  => 3,
                    'last30d' => 4,
                    'oldest'  => gmdate('Y-m-d\TH:i:sP', self::NOW - 20 * 86400),
                    'newest'  => gmdate('Y-m-d\TH:i:sP', self::NOW - 3600),
                ],
                'topReporterCountries' => [
                    ['code' => 'DE', 'count' => 2],
                    ['code' => 'IT', 'count' => 1],
                    ['code' => 'US', 'count' => 1],
                ],
                'distinctReporters' => 3,
            ], $summary);
        }

        public function test_percentages_can_add_up_to_more_than_100(): void
        {
            $summary = ReportSummary::summarize([self::report(60, [18, 22], 1, 'DE')], self::NOW);

            $this->assertSame(200, array_sum(array_column($summary['categories'], 'percent')));
        }

        public function test_only_the_top_five_countries_are_listed(): void
        {
            $reports = [];
            foreach (['AA', 'BB', 'CC', 'DD', 'EE', 'FF', 'GG'] as $i => $code) {
                for ($n = 0; $n <= $i; $n++) $reports[] = self::report(60, [18], "$code$n", $code);
            }

            $codes = array_column(ReportSummary::summarize($reports, self::NOW)['topReporterCountries'], 'code');

            $this->assertSame(['GG', 'FF', 'EE', 'DD', 'CC'], $codes);
        }

        public function test_empty_list(): void
        {
            $this->assertSame([
                'sampleSize' => 0,
                'categories' => [],
                'topCategory' => null,
                'timeline' => ['last24h' => 0, 'last7d' => 0, 'last30d' => 0, 'oldest' => null, 'newest' => null],
                'topReporterCountries' => [],
                'distinctReporters' => 0,
            ], ReportSummary::summarize([], self::NOW));
        }

        public function test_malformed_reports_do_not_break_the_summary(): void
        {
            $summary = ReportSummary::summarize([
                'garbage',
                ['categories' => 'nope', 'reportedAt' => 'not a date'],
                ['categories' => [99], 'reporterId' => null, 'reporterCountryCode' => null],
            ], self::NOW);

            $this->assertSame(2, $summary['sampleSize']);
            $this->assertSame([['id' => 99, 'name' => 'Unknown', 'count' => 1, 'percent' => 50]], $summary['categories']);
            $this->assertSame(0, $summary['distinctReporters']);
            $this->assertNull($summary['timeline']['oldest']);
        }

        public function test_apply_replaces_reports_with_the_summary(): void
        {
            $data = ReportSummary::apply(['ipAddress' => '198.51.100.1', 'reports' => [self::report(60, [22], 1, 'DE')]], self::NOW);

            $this->assertArrayNotHasKey('reports', $data);
            $this->assertSame(1, $data['reportsSummary']['sampleSize']);
            $this->assertSame('198.51.100.1', $data['ipAddress']);
        }

        public function test_apply_leaves_data_without_reports_unchanged(): void
        {
            $data = ['ipAddress' => '198.51.100.1', 'reportsSummary' => ['sampleSize' => 3]];

            $this->assertSame($data, ReportSummary::apply($data));
        }
    }
