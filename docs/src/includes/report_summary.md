# Reports summary

[`src/includes/report_summary.php`](../../../src/includes/report_summary.php)
turns the `reports` list of a verbose AbuseIPDB `/check` answer into a compact
`reportsSummary` object.

## API

```php
ReportSummary::apply(array $data, ?int $now = null): array
ReportSummary::summarize(array $reports, ?int $now = null): array
```

`apply()` takes a `data` object (or a cache entry), removes `reports` and adds
`reportsSummary`. Objects without `reports` are returned unchanged, so it is
safe to call it on data that was already summarized.

`summarize()` builds the summary:

- `sampleSize`: number of reports;
- `categories`: `{id, name, count, percent}` for every category, most frequent
  first (ties by id). A category repeated in one report counts once. `percent`
  is the share of reports containing the category, so the values can add up to
  more than 100. Names come from the
  [`abuse_category`](./enums.md#abuse_category) enum; unknown ids are named
  `Unknown`;
- `topCategory`: `{id, name, percent}` of the first category, or `null`;
- `timeline`: reports in the last 24 hours, 7 days and 30 days relative to
  `$now`, plus `oldest` and `newest` report time in UTC (`null` without
  readable dates);
- `topReporterCountries`: the `ReportSummary::TOP_COUNTRIES` (5) countries with
  most reports, `{code, count}`;
- `distinctReporters`: number of different `reporterId` values.

Malformed items (not arrays, missing fields, unreadable dates) are skipped or
ignored field by field, never fatal.
