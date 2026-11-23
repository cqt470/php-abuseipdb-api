<?php
    namespace Zexa\Includes;

    use Zexa\Includes\abuse_category;

    class ReportSummary{
        /** How many reporter countries are listed in topReporterCountries */
        public const TOP_COUNTRIES = 5;

        /**
         * Replaces the "reports" list of an AbuseIPDB "data" object with "reportsSummary".
         * The raw list can be up to 100 reports (~27 KB per IP); the summary is ~1 KB.
         * Objects without "reports" (already summarized, or not verbose) are returned unchanged.
         *
         * @param array $data the "data" object of a /check response, or a cache entry
         * @param int|null $now the reference time for the timeline, defaults to time()
         * @return array
         */
        public static function apply(array $data, ?int $now = null): array{
            if(!array_key_exists("reports", $data)) return $data;

            $reports = is_array($data["reports"]) ? $data["reports"] : [];
            unset($data["reports"]);
            $data["reportsSummary"] = self::summarize($reports, $now);

            return $data;
        }

        /**
         * Summarizes a list of AbuseIPDB reports.
         *
         * A report can have more than one category, so "percent" is the share of
         * reports that contain the category and the percentages can add up to more than 100.
         *
         * @param array $reports items with reportedAt, categories[], reporterId, reporterCountryCode
         * @param int|null $now the reference time for the timeline, defaults to time()
         * @return array
         */
        public static function summarize(array $reports, ?int $now = null): array{
            $now ??= time();
            $reports = array_values(array_filter($reports, "is_array"));
            $size = count($reports);

            $categories = [];
            $countries = [];
            $reporters = [];
            $timeline = ["last24h" => 0, "last7d" => 0, "last30d" => 0];
            $oldest = null;
            $newest = null;

            foreach($reports as $report){
                // the same category twice in one report counts once
                $ids = array_unique(array_filter((array)($report["categories"] ?? []), "is_int"));
                foreach($ids as $id) $categories[$id] = ($categories[$id] ?? 0) + 1;

                $country = $report["reporterCountryCode"] ?? null;
                if(is_string($country) && $country !== "") $countries[$country] = ($countries[$country] ?? 0) + 1;

                $reporter = $report["reporterId"] ?? null;
                if(is_int($reporter) || is_string($reporter)) $reporters[$reporter] = true;

                $time = is_string($report["reportedAt"] ?? null) ? strtotime($report["reportedAt"]) : false;
                if($time === false) continue;

                $age = $now - $time;
                if($age <= 86400) $timeline["last24h"]++;
                if($age <= 86400 * 7) $timeline["last7d"]++;
                if($age <= 86400 * 30) $timeline["last30d"]++;
                $oldest = $oldest === null ? $time : min($oldest, $time);
                $newest = $newest === null ? $time : max($newest, $time);
            }

            // most frequent first, ties by id / code so the output is stable
            uksort($categories, fn($a, $b) => [$categories[$b], $a] <=> [$categories[$a], $b]);
            uksort($countries, fn($a, $b) => [$countries[$b], (string)$a] <=> [$countries[$a], (string)$b]);

            $list = [];
            foreach($categories as $id => $count){
                $list[] = [
                    "id" => $id,
                    "name" => abuse_category::from_input($id)?->label() ?? "Unknown",
                    "count" => $count,
                    "percent" => (int)round($count / $size * 100),
                ];
            }

            $top = [];
            foreach(array_slice($countries, 0, self::TOP_COUNTRIES, true) as $code => $count){
                $top[] = ["code" => (string)$code, "count" => $count];
            }

            return [
                "sampleSize" => $size,
                "categories" => $list,
                "topCategory" => $list ? ["id" => $list[0]["id"], "name" => $list[0]["name"], "percent" => $list[0]["percent"]] : null,
                "timeline" => $timeline + [
                    "oldest" => $oldest === null ? null : gmdate("Y-m-d\TH:i:sP", $oldest),
                    "newest" => $newest === null ? null : gmdate("Y-m-d\TH:i:sP", $newest),
                ],
                "topReporterCountries" => $top,
                "distinctReporters" => count($reporters),
            ];
        }
    }
