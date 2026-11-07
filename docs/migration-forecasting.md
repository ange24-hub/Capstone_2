# Verified arrival, departure and net-migration forecasting

Open **Migration Monitoring → Forecast**. The overview shows estimated arrivals, departures and their signed net difference for the same target month. Predictions count movement events, not unique people or people predicted to move. Net migration is not total population change and does not include births or deaths. The older RBI entry-balance indicator is not an input. No demographic or individual-resident risk model is used.

## Reporting completeness

Select a barangay and review each completed month against both actual arrival and departure logs. Confirm that all genuine movements are encoded, dates are correct and duplicate events have been reviewed. A zero count enters a series only after staff explicitly verify zero events of that type. No existing reporting month is automatically confirmed by this release. Legacy departure-only confirmations continue to apply only to departures; they never imply arrival completeness. The new form explicitly attests to both logs and submits both source fingerprints. Either stale fingerprint or duplicate series rejects the entire confirmation before any changes are saved. Reopening from this form reopens both series.

Approved secretaries can confirm only their assigned barangay. Approved municipal staff can review a selected barangay. Residents and pending staff cannot confirm reporting. Every confirmation and reopening adds a Registry Activity History entry with the actor and prior/new reporting metadata. The confirmation form includes the source fingerprint and refuses a stale browser submission.

The additive `migration_reporting_periods` table stores the month and independent count, fingerprint, verifier, review time and note for each series. Arrival fields are nullable on upgrade and start unverified. Fingerprints cover event IDs, resident links, dates and update timestamps. Adding, deleting or editing source events invalidates the affected series on the next read; invalidating either component makes net unavailable. Possible duplicates for the same inhabitant, movement type and date block joint verification until staff review the records. Reopening excludes the month from training immediately. Confirmations never add, change or delete migration events.

Demo events marked by the demo reason, resident marker or `DEMO-MIGRATION-V1` household are excluded from training and evaluation. They remain visibly labelled in ordinary demo reports until explicitly cleaned up. Source counts describe recorded events, not confirmed reporting completeness.

## Model fitting and evaluation

The native PHP forecasting engine fits aggregate monthly counts on demand. Python, Ollama and the bundled synthetic-date Gradient Boosting artifact are not used by this dashboard. It works on the existing PHP hosting runtime.

The input is the latest uninterrupted run of complete, unchanged reporting months, ending at the last completed calendar quarter. Source windows refresh every three months: January 1, April 1, July 1 and October 1, on the first request in the new quarter. No scheduler or background service is required. Within a quarter, the source cutoff and model-selection horizon stay fixed; the next-calendar-month target still advances each month. A missing, reopened, stale or duplicate-affected month breaks the run immediately, including within a quarter. A municipal aggregate requires the same confirmations from every configured barangay; a subset must not silently become a municipal total. The review window is 36 completed months through the quarter cutoff. Newer months enter this review window in the next quarter. Corrections and re-verification of older source records may change a result immediately; quarterly cadence does not override data integrity.

Candidate methods are last-month persistence, recursive three-month moving average, simple exponential smoothing (alpha 0.2, 0.5, 0.8), and Holt linear trend (alpha/beta 0.3/0.1 or 0.6/0.2). Negative fitted forecasts are clamped to zero. This release makes no seasonal claim.

These models are selected and fitted separately for arrivals and departures. Each component uses its own latest consecutive verified history, ending at the same source cutoff. Net is available only when both components are ready at the same horizon with matching held-out target months. **Net = predicted arrivals − predicted departures**, without clamping negative values. Each held-out actual net is actual arrivals minus actual departures. Net MAE is the average absolute error of those net predictions, not the sum of component MAEs. Its benchmark is the difference of the two moving-average baselines. The screen reports better, equal or worse performance independently for arrivals, departures and net. Selecting components individually does not guarantee the lowest possible net error.

The operational minimum is six initial training months, at least three earlier rolling validation targets, and three final held-out test targets. Additional gaps preserve the forecast horizon. Earlier validation windows choose a fixed method and parameters; final test actuals never participate in that choice. Each test prediction is fitted using only the months available at its forecast origin. Later test origins may use earlier test actuals once those would have become known. Ties prefer the moving-average benchmark. The final future estimate is refitted on all available verified history after evaluation.

- Current-year view: the target is the **next calendar month**, using source records through the previous quarter. It requires at least **18 consecutive verified months**, covering the maximum four-month horizon needed within the quarter. Model selection always uses that four-month horizon so the selected method remains fixed throughout the quarter. Held-out evaluation uses the actual target's two-, three- or four-month horizon. For example, in October, November and December the source ends in September, the targets are November, December and January respectively, and the next source refresh is January 1. Model-selection validation MAE and validation-month counts refer to the four-month selection exercise; held-out MAE refers to the displayed forecast horizon.
- Historical-year view: source history ends in December of that year and the target is the following January. A one-month exercise requires at least **12 consecutive verified months**.
- Future reporting years: no estimate is produced.

These minimums apply to each series and are implementation readiness gates, not guarantees of statistical reliability. The dashboard shows mean absolute error (MAE), moving-average baseline MAE, exact held-out actual/predicted counts, and comparison charts for every available series. One component may be available while net remains unavailable. It reports when forecasts match or perform worse than the baseline instead of claiming universal improvement. Three held-out months are limited evidence; verify more history and prospective results before operational reliance. MAE is an error in event counts, not an accuracy percentage or a calibrated confidence interval. More advanced models or software tests passing are not evidence of improved real-world forecasting accuracy.

The assistant uses the same scoped report for next-calendar-month arrival, departure and net forecasts in English/Bisaya. A request for net never silently substitutes a departure-only estimate. Unsupported horizons and explicit seasonal requests receive guidance. Retrieved source records are not sent to a language model.

## Installation and audits

Normal installation includes both forecast migrations. Existing Docker deployments run `php artisan migration:prepare-forecast-storage` at startup when general migrations are disabled. This idempotent command applies only the reporting table migration and `2026_11_05_010000_add_arrival_verification_to_migration_reporting_periods.php`, which adds nullable arrival verification fields. It preserves existing departure checks, creates no users or movement records, and leaves all other migrations opt-in. On a new, empty database it waits for the normal installation workflow. Existing backups, database identities, keys and volumes stay in place.

For an existing non-Docker installation:

```sh
php artisan migration:prepare-forecast-storage
php artisan migration:forecast-audit
php artisan migration:forecast-audit --barangay=16
```

The audit is read-only and outputs aggregate coverage, excluded demo totals and available evaluation; it contains no resident names, addresses or coordinates. Local results are not evidence of the hosted database's contents. `scripts/check-migration-prediction.php` now invokes this aggregate audit. `start-rbim.bat` prepares reporting storage and no longer launches the legacy Python predictor.

## Validation

### Separate synthetic training experiment

Run `php scripts/train-demo-forecast.php` to fit and evaluate the same PHP forecasting engine using a reproducible, explicitly synthetic dataset. This standalone runner does not bootstrap Laravel, access the database, confirm reporting periods, update the legacy Python artifact, or change live forecasts.

Each run creates a new private `storage/app/forecast-demo/` folder containing 108 monthly rows (three fictional scenarios, 36 months each), evaluation metrics, held-out predictions, a JSON result manifest with checksums, and a readable report. Scenarios cover stable counts, a gradual trend, and an abrupt change. A fixed seed makes comparisons reproducible; the counts are not estimated from actual residents.

The experiment selects methods using earlier rolling validation at the dashboard's four-month selection horizon. It evaluates July–September 2026 targets at a three-month horizon, using April–June 2026 training cutoffs respectively. It then refits on all 36 months for a December 2026 synthetic forecast. The final three target actuals never choose the model. The in-memory completeness flags are simulation inputs, not staff attestations, and are never stored in the reporting database.

Read test MAE alongside the moving-average baseline and retain worse/equal results. MAE measures error in event counts, not an accuracy percentage. These three held-out targets per scenario demonstrate the workflow only; they cannot establish performance on actual barangay data. Do not import the demo CSV as verified production records.

Approved barangay and municipal staff can open **Migration trends → Demo prediction analysis** (`/migration-monitoring/demo`). This separate read-only page shows the saved experiment at `resources/demo/migration-forecast.json`, with scenario tabs, clearly labeled synthetic actual-versus-predicted graphs, moving-average comparisons, signed net forecasts, and exact test values. The December 2026 target is a fixed experiment date, not a live next-month estimate. Real records and reporting confirmations are never read or modified by the demo controller. Screenshots should retain the **DEMO DATA ONLY** banner and graph legend. To update the displayed experiment, rerun the script and explicitly review/copy its JSON result into that resource; deployment never runs training automatically.

`MigrationForecastEngineTest` checks held-out leakage, trend fitting, known zeros, chronological continuity and MAE arithmetic. `MigrationNetForecastTest` verifies signed net arithmetic, matched targets and independently measured net errors, including worse-than-baseline results. `NetMigrationForecastTest` exercises joint readiness, atomic dual-fingerprint confirmation, arrival changes, duplicates, synthetic exclusion, municipal completeness, English/Bisaya answers and preservation of old checks on upgrade. `VerifiedMigrationForecastTest` retains departure compatibility and quarterly rollover coverage. The existing dashboard/report tests still protect role and year filters. Model accuracy on fixture data is not evidence of accuracy on real barangay records.

Evaluation follows the time-ordered validation principles in [Forecasting: Principles and Practice](https://otexts.com/fpp3/tscv.html).
