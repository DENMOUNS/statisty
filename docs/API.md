API Reference — Statisty (package)

Base URL: `/statisty`

1) Metrics

GET `/statisty/metrics/{model}`

Query parameters:
- `type` (string): one of `count`, `sum`, `average`, `min`, `max`, `funnel`, `cohort`, `anomaly`.
- `field` (string): numeric field for `sum/average/min/max`.
- `date_column` (string): column used for time filtering/aggregation (default `created_at`).
- `date_from`, `date_to` (string): date filter.
- `filters` (array): map of column => value pairs.

Funnel:
- `type=funnel`
- `steps` JSON parameter: array of step objects `{column, operator, value}`.
- Example: `?type=funnel&steps=[{"column":"step","operator":"=","value":"A"},{"column":"step","operator":"=","value":"B"}]`

Cohort:
- `type=cohort` + `period=day|week|month` + `periods=integer`
- Returns `labels` (cohort keys) and `matrix` (rows of counts per subsequent period).

Anomaly detection:
- `type=anomaly` + `field` + `period` + `threshold` (z-score default 3.0) + optional `alert=1` to persist the detection in the package cache.
- Response: `{anomalies: [...], series: {labels: [...], data: [...]}}`

2) Tables
GET `/statisty/tables/{model}`

Query params:
- `columns[]` list of columns (supports relation.field)
- `per_page`, `sort`, `dir`, `q` (search), `filters[]`
- `export=csv` returns CSV attachment of all rows

3) Charts
Programmatic usage via `ChartDataGenerator::generateFromModel($model, $field, $dateColumn, $options)`
Options include:
- `period=day|week|month`
- `transform=moving_average&window=3`
- `cumulative=1`
- `percentile=90`
- `histogram[bins]=10`

Notes
- Authorization: enable `statisty.security.enforce_authorization` to require policy checks.
- Caching: KPI and dashboard caching rely on `ProfilingCache` when available; workspace options control TTL.

Examples

Count KPI for `App\\Models\\Order`:
```
GET /statisty/metrics/App\\Models\\Order?type=count
```

Sum KPI for `amount`:
```
GET /statisty/metrics/App\\Models\\Order?type=sum&field=amount
```

Anomaly detection for `value` field:
```
GET /statisty/metrics/App\\Models\\Event?type=anomaly&field=value&period=day&threshold=3&alert=1
```
