# Migration trends and demo data

Open `/migration-monitoring`. The year and barangay filters control recorded trends. Secretaries always see their assigned barangay. Forecast estimates use completed calendar months: the last completed month for the current year, or December for a past year. A past-year estimate is for the following January. Future years and empty recent history do not produce an estimate.

The local model is optional. If it is offline, returns an error, or supplies an invalid result, the dashboard uses the three-month average of recorded departures, labelled with its method. Neither method is a validated population forecast. No-record months contribute zero recorded events; completeness is unverified.

## Removable sample records

Run `php artisan migration:demo` locally to add synthetic arrivals and departures for the current month and previous 12 months in every existing barangay. Use `--barangay=ID` to limit creation to one barangay. Each event has a separate clearly named demo resident under `DEMO-MIGRATION-V1`. Data uses the marker `RBIM migration demo v1`. Re-running skips existing demo households. Creation is disabled in production and is never part of normal database seeding.

Demo profiles affect population totals and reports while installed. The migration dashboard displays a demo notice. These are test records, not imported historical evidence or model evaluation data. Do not edit demo profiles into real residents.

Before deployment, run:

```sh
php artisan migration:demo --remove
```

This removes tagged migration events and their tagged demo residents and empty demo households. It preserves other migration events and real residents. If staff linked a demo resident to a user, document request, RBI record, new-inhabitant record, or another movement event, cleanup preserves that profile and prints a message for manual review. Removal can be repeated and works in production. It does not reset or truncate the database.

## Optional Python model

With Flask, pandas, joblib and the model's compatible scikit-learn version installed, run `python ml/predictor.py` from the project root. The model file is resolved relative to the script. Laravel uses `MIGRATION_PREDICTION_URL` (default `http://127.0.0.1:5001`). The existing model used synthetic training dates, so its output remains a prototype estimate.

## Regression checks

```sh
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --filter="MigrationForecastTest|MigrationReportTest|test_monthly_migration_filters_year_and_barangay_and_includes_empty_months"
```

These use the isolated in-memory SQLite test database, including demo creation/removal checks.
