# RBIM: WMA migration forecasting, without ML

## Main feature: Migration Trends; optional provisional estimates

Migration Trends, recent events and newly generated migration reports/PDFs share `RecordedMigrationHistory`. Arrivals and departures both use actual dated `migration_records` plus unlinked dated movement-ledger observations. RBI save/import/encoding dates are not physical movement dates. Future-dated events, demo data and mismatched resident/barangay records are excluded. Linked ledger mirrors do not add another event; the same barangay/resident/direction/day counts once, with duplicate originals preserved. Separate movement dates remain separate events.

Values are recorded-event counts, not a claim of complete migration coverage. Unknown monthly counts are shown as gaps/dashes. Zero is displayed only for completed months with matching verified source counts/hashes, synchronized originals and no duplicate inflation. Municipal zero requires coverage for every barangay in scope. An observed nonzero count can be displayed while its completeness remains unconfirmed. Annual totals are totals of recorded events, not proof of a population increase/decrease. Reading trends never syncs, backfills or edits source reports.

The population tool is labeled **Provisional Population Estimate**. Its WMA equations, historical report contents and existing source-eligibility checks remain unchanged. Its reported-history and provisional-estimate graph labels explicitly distinguish observations from estimates. Insufficient history stays unavailable; sample-data correctness tests do not establish real-world predictive accuracy.

## Mga file nga nagpatuman sa request

- `database/migrations/2026_11_09_000001_create_migration_logs_table.php`
- `database/migrations/2026_11_09_000002_create_forecast_logs_table.php`
- `app/Services/MigrationPredictionService.php`
- `app/Http/Controllers/PredictionDashboardController.php`
- `resources/views/barangay/dashboard/prediction.blade.php`
- `routes/web.php`: `prediction.dashboard`, `prediction.store`, `prediction.verify`, `prediction.sync`, `prediction.movements.store`
- `config/migration_forecast.php`: window, timezone ug configurable baseline rate.

## Database ug source dates

Ang existing RBIM residents table kay **inhabitants**, busa `migration_logs.resident_id` foreign key ngadto niini. Dili maghimo og duplicate residents table. Ang source movement table kay `migration_records`; ang optional unique `source_record_id` nagpugong sa duplicate imports. Dili hilabtan o tanggalon ang resident profiles sa installation.

Ang `transaction_date` kinahanglan actual movement date. Ang RBI save date, import date, household registration date ug ambiguous remarks dili himuong migration dates. Ang tagged demo events/profiles gi-exclude. `Sync existing dated movement records` mokopya sa existing movement dates; ang future Eloquent movement creates/updates automatic nga ma-sync.

Ang forecast table adunay requested fields ug audit fields: `issued_at`, `method`, `history_snapshot`, `actual_verified_at`, `actual_source_hash`, `verified_by`. Nullable ang predicted value aron mahimong maka-confirm og historical actual-only month **nga walay fabricated historical prediction**. Unique ang barangay/month/type.

## Forecasting rules

1. Ang target kay **sunod nga calendar month**, Philippine timezone.
2. Ang history mohunong sa **last completed calendar month**. Pananglitan: forecast issued November 8 para December, gamit lamang ang history through October. Wala i-apil ang partial November. Kini adunay usa ka incomplete-month gap ug ang accuracy evaluation mosukod niini nga protocol.
3. Gikan sa last completed month, gamiton ang labing bag-ong 3–6 consecutive verified months. Mohunong sa first missing/unverified month. Ang zero nga confirmed complete valid nga observation; ang walay entries nga wala pa na-confirm unknown.
4. WMA weights kay `1, 2, ..., n`, oldest ngadto sa newest. Formula: `sum(count × weight) / sum(weight)`. Counts `[2,4,8]` → `34/6 = 5.6667`.
5. Kung kulang sa 3 verified consecutive months, ang default one-period assumption kay `P × ((1+0.001)^1 − 1) = P × 0.001` sa matag direction. `P` kay active registered residents excluding demo, matching Consolidated. Dili kini verified migration rate o net population growth model. Walay datos nga nagpamatuod nga parehas ang in/out rates; transparent default assumption lang kini.
6. Walay parameter training, SVR, Random Forest, Python prediction service, external HTTP call o paid API sa bag-ong forecast ug interpretation flow. Fractional expected event counts normal; 4 decimal places ang saved values, 2 ang display.

## Pag-save ug accuracy

### Semestral population source integration

Ang population projection nagbasa sa immutable historical archives ug sa barangay reports nga na-submit na sa municipal workflow. Completed semesters ra ang mahimong actual history. Sa submitted digital reports, ang saved `generatedAt` kinahanglan adunay timezone ug sakop sa gi-label nga year/semester gamit ang Philippine calendar; dili mahimong historical observation ang latest-registry snapshot nga gi-label lang og laing period. Ang submission kinahanglan dili future-dated o mas una kaysa iyang captured observation, accounting for the database timestamp's second precision.

Gi-check ang snapshot hash, headline population, stored record count ug age-group totals before gamiton ang submitted source. Conflicting totals block the forecast; unknown or invalid source values never become zero. Equal totals for the same semester count once, keeping the archive reference when present. Ineligible observations are explained inside **Calculation & source reports**. The projection never updates, deletes or rehashes saved reports. Existing archive age-count discrepancies remain visible for review rather than being silently corrected.

Ang default reporting period, forecast cutoff ug movement-date validation naggamit sa configured Philippine calendar. Stored timestamps retain their existing UTC format, and the supplied movement date is stored without shifting its day.

These sources are dated reported observations, not proof of an end-of-semester census or future predictive accuracy. Population projection MAPE remains unavailable without a separately issued forecast and verified outcome.

Preview GET requests walay forecast writes. Pislita ang **Save first-issued in/out forecasts** aron mag-save before the target month. Ang unang value ug history snapshot dili ma-overwrite sa repeated save/refresh. Ang monthly command mahimo usab gamiton; walay backdated issue function.

Human mahuman ang target month, i-review ang dated movement records ug i-confirm nga kompleto ang month. Ang actual value gikan sa database count, dili arbitrary number nga gi-type sa staff. Blocked ang verification kung adunay duplicate resident/type/date events, wala pa ma-sync nga source records o stale source fingerprint. Kung mausab ang source, dili na gamiton ang old verification hangtod ma-review pag-usab.

`MAPE = mean(abs(actual − predicted) / actual) × 100` sa eligible months nga actual > 0.

`Accuracy score = 100 − MAPE`. Dili kini probability o garantiya; puwedeng negative kung MAPE > 100%. Dili i-clamp aron dili matago ang dagkong errors.

Actual = 0 → excluded sa MAPE ug klarong ihap sa excluded-zero months. Walay artificial denominator of 1. MAE ug RMSE nag-apil sa tanang verified evaluated months, apil ang zero. Walay eligible actuals → **Not yet measurable**, dili 100%. Only predictions issued before the target month enter evaluation. Historical actual-only rows dili test predictions.

Ang results gikan sa automated test fixtures mga software-validation results; dili sila accuracy evidence sa actual barangay. Kinahanglan mag-save ug tinuod nga future forecasts ug maghulat sa subsequent confirmed actual months aron adunay empirical accuracy.

## Access ug paggamit

- Approved municipal administrator: makapili og barangay.
- Approved barangay staff: iyang assigned barangay ra; forged URL/POST barangay IDs rejected.
- Residents, pending accounts, ug unassigned barangay staff: walay access.
- Route: `GET /prediction-dashboard`; view: `barangay.dashboard.prediction`.
- Monthly verification, record sync, movement recording ug forecast issuance kay authenticated CSRF-protected POST actions.
- Daang ML prediction URLs redirect sa statistical dashboard; daang ML write endpoints return HTTP 410.

Workflow: pilia ang barangay → sync existing dated sources → encode/review verified movement dates → confirm complete historical months → preview WMA/baseline → save first issue → human sa target month, confirm actuals → review MAPE/MAE/RMSE.

## Deployment

Existing installation, additive storage only:

```shell
php artisan migration:prepare-statistical-storage
php artisan view:clear
php artisan route:cache
php artisan view:cache
```

Fresh installation: run the normal `php artisan migrate --force` process after configuring the existing RBIM database. Ayaw gamita ang `migrate:fresh` sa production. Docker entrypoint prepares only these additive tables for an existing installation; walay demo seed o destructive data reset.

Optional configuration in `.env`:

```dotenv
MIGRATION_BASELINE_RATE=0.001
```

After changing config: `php artisan config:cache`.

Manual batch issue:

```shell
php artisan migration:issue-statistical-forecasts
php artisan migration:issue-statistical-forecasts --barangay=14
```

Ang registered Laravel schedule kay day 28, 23:00 Asia/Manila. Kinahanglan configured ang server scheduler aron automatic kini; wala mag-assume nga ang deploy-on-push adunay cron. Standard server cron:

```cron
* * * * * cd /path/to/RBIM && php artisan schedule:run >> /dev/null 2>&1
```

Sa Docker, gamita ang scheduler process sa app container o host scheduler nga mo-execute sa `php artisan schedule:run` sulod sa app container. Kung walay scheduler, magamit gihapon ang save button.

## Mubo nga defense explanation

“Ang among system wala mogamit og machine learning. Weighted Moving Average among statistical forecasting method: mas bug-at ang bag-ong verified monthly migration counts kaysa karaan. Kung kulang ang history, ipakita namo ang population-based assumption ug klarong baseline label. Dili namo himuong zero ang unknown months, dili mag-imbento og movement dates, ug dili maghimo og past predictions human makita ang actual result. Gi-save ang forecast una sa target month ug gi-compare sa verified actuals gamit ang MAPE. Dili applicable ang MAPE sa zero actual, busa among ipakita usab ang MAE ug RMSE. Ang Cebuano explanation rule-based PHP text, dili AI-generated output.”
