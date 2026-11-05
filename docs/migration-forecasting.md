# Verified out-migration forecasting

Open **Migration Monitoring → Forecast**. Predictions count departure events, not unique people or people predicted to move. No demographic or individual-resident risk model is used.

## Reporting completeness

Select a barangay and review each completed month against the actual transfer log. Confirm that all genuine departures are encoded, dates are correct and duplicate events have been reviewed. A zero count enters training only after staff explicitly verify a zero-departure month. No existing reporting month is automatically confirmed by this release.

Approved secretaries can confirm only their assigned barangay. Approved municipal staff can review a selected barangay. Residents and pending staff cannot confirm reporting. Every confirmation and reopening adds a Registry Activity History entry with the actor and prior/new reporting metadata. The confirmation form includes the source fingerprint and refuses a stale browser submission.

The additive `migration_reporting_periods` table stores the month, real departure count, source fingerprint, verifier, review time and optional note. Fingerprints cover the event IDs, resident links, dates and update timestamps. Adding, deleting or editing source events invalidates their month's completeness on the next read. Possible duplicate departures for the same inhabitant and date block verification until staff review the underlying records. Reopening a month excludes it from training immediately. Confirmations never add, change or delete migration events.

Demo events marked by the demo reason, resident marker or `DEMO-MIGRATION-V1` household are excluded from training and evaluation. They remain visibly labelled in ordinary demo reports until explicitly cleaned up. Source counts describe recorded events, not confirmed reporting completeness.

## Model fitting and evaluation

The native PHP forecasting engine fits aggregate monthly counts on demand. Python, Ollama and the bundled synthetic-date Gradient Boosting artifact are not used by this dashboard. It works on the existing PHP hosting runtime.

The input is the latest uninterrupted run of complete, unchanged reporting months, ending at the last completed calendar month. A missing, reopened, stale or duplicate-affected month breaks the run. A municipal aggregate requires the same confirmations from every configured barangay; a subset must not silently become a municipal total. The review window is 36 completed months.

Candidate methods are last-month persistence, recursive three-month moving average, simple exponential smoothing (alpha 0.2, 0.5, 0.8), and Holt linear trend (alpha/beta 0.3/0.1 or 0.6/0.2). Negative fitted forecasts are clamped to zero. This release makes no seasonal claim.

The operational minimum is six initial training months, at least three earlier rolling validation targets, and three final held-out test targets. Additional gaps preserve the forecast horizon. Earlier validation windows choose a fixed method and parameters; final test actuals never participate in that choice. Each test prediction is fitted using only the months available at its forecast origin. Later test origins may use earlier test actuals once those would have become known. Ties prefer the moving-average benchmark. The final future estimate is refitted on all available verified history after evaluation.

- Current-year view: the target is the **next calendar month**. With the current month incomplete, this is a two-month forecast from the latest completed source month. It requires at least **14 consecutive verified months**. For example, on November 5 the source ends in October and the target is December.
- Historical-year view: source history ends in December of that year and the target is the following January. A one-month exercise requires at least **12 consecutive verified months**.
- Future reporting years: no estimate is produced.

These minimums are implementation readiness gates, not guarantees of statistical reliability. The dashboard shows the selected model's mean absolute error (MAE), the three-month baseline MAE, exact held-out actual/predicted counts, and a comparison chart. It reports when the selected method matches or performs worse than the baseline instead of claiming universal improvement. Three held-out months are limited evidence; verify more history and prospective results before operational reliance. MAE is an error in event counts, not an accuracy percentage or a calibrated confidence interval.

The assistant uses the same scoped report for next-calendar-month departure forecasts in English/Bisaya. Unsupported horizons, arrival forecasts and explicit seasonal requests receive guidance instead of a different forecast. Retrieved source records are not sent to a language model.

## Installation and audits

Normal installation includes the new migration. Existing Docker deployments run `php artisan migration:prepare-forecast-storage` at startup when general migrations are disabled. This idempotent command applies only `2026_11_05_000000_create_migration_reporting_periods_table.php`, creates no users or movement records and leaves all other migrations opt-in. On a new, empty database it waits for the normal installation workflow. Existing backups, database identities, keys and volumes stay in place.

For an existing non-Docker installation:

```sh
php artisan migration:prepare-forecast-storage
php artisan migration:forecast-audit
php artisan migration:forecast-audit --barangay=16
```

The audit is read-only and outputs aggregate coverage, excluded demo totals and available evaluation; it contains no resident names, addresses or coordinates. Local results are not evidence of the hosted database's contents. `scripts/check-migration-prediction.php` now invokes this aggregate audit. `start-rbim.bat` prepares reporting storage and no longer launches the legacy Python predictor.

## Validation

`MigrationForecastEngineTest` checks held-out leakage, trend fitting, known zeros, chronological continuity and MAE arithmetic. `VerifiedMigrationForecastTest` exercises readiness, scope, attestations, changed records, duplicates, synthetic-data exclusion, municipal completeness, audit entries, native assistant answers and additive installation. The existing dashboard/report tests still protect role and year filters. Model accuracy on fixture data is not evidence of accuracy on real barangay records.

Evaluation follows the time-ordered validation principles in [Forecasting: Principles and Practice](https://otexts.com/fpp3/tscv.html).
