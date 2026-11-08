# Migration prediction and reporting

Approved municipal administrators use **Prediction analytics** to open the main forecast screen. Barangay accounts retain **Migration Trends** only.

## Current prediction

Select the current reporting year and Cabascan or Cawayan, then choose **Show forecast**. A separate experimental SVR card shows only the selected barangay's classified arrival-record estimate. It does not replace verified reporting counts, departure estimates or net migration.

The active estimator is an RBF SVR with C=10, epsilon=1 and gamma=0.1, refitted on 12 eligible saved-classification samples from 2020–2021. Numeric scaling, one-hot barangay encoding and fitted support vectors are exported for native PHP inference. Twelve reference cases match scikit-learn within 1e-10. This verifies matching computation, not real-world accuracy.

The December 2026 output is approximately 1.54 per covered barangay. Last observed inputs are Cabascan June 2021 count 1 and Cawayan September 2021 count 2. The large gaps drive the RBF output toward its intercept. Similar estimates do not establish stable future migration. Other barangays are unavailable rather than borrowing another area's output.

Historical sample MAE 1.9333 / RMSE 2.5933 came from a separate seven-row training fit and five later test rows. The additional training-mean baseline had MAE 1.7143 / RMSE 2.6045, so SVR did not beat it on MAE. These are previously inspected retrospective administrative-record samples, not validated physical municipal migration.

Future MAE/RMSE remain unavailable. Physical municipal arrivals require origin/boundary and complete-period confirmation. Out-migration has only one dated non-demo event and no complete verified departure periods, so no out-migration SVR or invented dates are supplied.

## Kept workflows

- Main municipal forecast, barangay filter and Migration Trends.
- Actual RBI forms, report history, source classifications and movement recording.
- Existing verified-history forecasting and completeness checks.
- Defense guide and aggregate model evidence inside the collapsed **Model details for the defense** section.
- **Save this forecast**, a simple saved-result view and immutable evidence download. Stored forecasts retain original inputs, target, model hashes and saving administrator even after the calendar/model changes.
- Active SVR training, numeric export, source reconstruction, data import and integrity verification utilities.

The private inference artifact is at `storage/app/migration-reconstruction/pooled-svr-inference.json`. Its matching aggregate source evidence and private review queue remain under the same storage area. The trained joblib, numerical export and original evaluation are retained in `output/prediction-analysis/pooled-actual-review-20261108`.

Deployments without those private local files use the reviewed numeric export and compact aggregate metadata bundled under `resources/forecast-models/`. Existing private host exports take precedence and mismatches fail closed. The release contains no raw RBI entries, resident identities, database dumps or private review queues. This fallback does not copy local database records into the online database.

To retrain, review actual source labels first, then use `scripts/reconstruct_pooled_migration.php`, `ml/train_pooled_actual_svr.py`, `ml/verify_pooled_actual_svr.py`, `ml/export_pooled_svr_inference.py` and `scripts/activate_pooled_svr_inference.php`. Use fresh run/export names; never overwrite test outcomes or turn missing months into zero. `scripts/verify_pooled_source_integrity.php` verifies source preservation against the retained audit.

## Retired experiments

Old scenario/demo pages, uploaded-model previews, historical lab screens, synthetic training searches, superseded reports and their dedicated tests were removed from active app/code folders. They are recoverable in `storage/app/retired-experiments/cleanup-20261108`; `manifest.json` lists original paths and code-file hashes, and `before-edits` preserves edited originals.

Old base-page bookmarks redirect approved administrators to the main forecast with supported filters. Scenario-writing endpoints no longer run. Actual RBI records, import sources, original workbooks, import backups and active model artifacts were not removed.

Tests cover the active Cabascan filter, permission isolation, source preservation, inference parity, saved evidence, retired-page redirects and the absence of experimental menu clutter.
