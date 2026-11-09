# Superseded forecast UI contracts

The `.txt` files preserve the old assertions for the retired SVR and quarterly/seasonal forecasting screens. They are historical references, not executable specifications for the new WMA feature. These methods required the old screens to return SVR estimates, old evidence downloads, 12–18 month model selection, or municipal-only forecast access, which conflict with the new explicit No ML / WMA / scoped barangay-access requirement.

Current behavior is covered by `StatisticalMigrationForecastTest`, `RetiredMlForecastRoutesTest`, and the updated navigation/access tests. Existing source-data, reporting-period permission, population, and algorithm unit tests remain executable. No production data is present in these reference fixtures.
