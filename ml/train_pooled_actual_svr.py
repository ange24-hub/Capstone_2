"""Pooled actual-record SVR experiment; never relabel it as verified municipal migration."""
import argparse
import hashlib
import html
import json
from pathlib import Path
import sys
from datetime import datetime, timezone

if __package__ in (None, ''):
    sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import joblib
import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.model_selection import GridSearchCV
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler
from sklearn.base import clone
from ml.converged_svr import CheckedSVR

GRID = {'svr__C': [.1, 1., 10., 100.], 'svr__epsilon': [.1, 1.], 'svr__gamma': ['scale', .1]}
NUMERIC = ['month_sin', 'month_cos', 'previous_observed', 'previous_missing', 'gap_months']
TARGET = 'existing_classified_arrival_entries_in_observed_source_month'


def write(path, data):
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False, allow_nan=False), encoding='utf-8')


def metrics(actual, predicted):
    error = np.asarray(actual, dtype=float) - np.asarray(predicted, dtype=float)
    return {'mae': float(np.mean(abs(error))), 'rmse': float(np.sqrt(np.mean(error**2)))}


def feature_frame(rows):
    past = {}
    features = []
    for row in rows:
        date = datetime.strptime(row['month'], '%Y-%m')
        area = str(row['barangay_id'])
        previous = past.get(area)
        gap = 0 if previous is None else (date.year-previous[0].year)*12 + date.month-previous[0].month
        features.append({'barangay': area, 'month_sin': np.sin(2*np.pi*date.month/12),
                         'month_cos': np.cos(2*np.pi*date.month/12),
                         'previous_observed': 0. if previous is None else previous[1],
                         'previous_missing': int(previous is None), 'gap_months': gap})
        past[area] = (date, row['count'])
    return pd.DataFrame(features)


def protocol():
    return {'created_at_utc': datetime.now(timezone.utc).isoformat(), 'grid': GRID,
            'selection': 'Earlier-date CV MAE; ties use earlier-date CV RMSE. Final-test scores never choose settings.',
            'final_test': 'Last three distinct observed calendar months, all barangays on each date together.',
            'validation': 'Two expanding folds over the earlier distinct months, two validation dates per fold.',
            'target': TARGET, 'synthetic_rows': 0, 'age_inferred_labels': 0, 'imputed_target_months': 0,
            'not_a_fresh_unseen_holdout': True,
            'limitations': ['Retrospective current-snapshot experiment; these historical outcomes have been inspected before.',
                'Existing saved classifications are not independently verified migration ground truth.',
                'Only classified observed source months are eligible, so results are conditional on source selection.',
                'Origins are unknown; targets are barangay-recorded entries, not external municipal migration totals.',
                'Gaps are not target zeros. Previous observed value is not necessarily previous calendar month.',
                'Frozen model uses earlier test actuals for later feature inputs only after their dates.',
                'No claim that current classifications were available at historical forecast origins.']}


def train_sample(source, out):
    rows = sorted(source['experimental_arrival_samples'], key=lambda r: (r['month'], r['barangay_id']))
    if len({(r['month'], r['barangay_id']) for r in rows}) != len(rows):
        raise ValueError('Duplicate area-month targets')
    for row in rows:
        datetime.strptime(row['month'], '%Y-%m')
        if type(row['count']) is not int or row['count'] < 0 or row.get('target') != TARGET:
            raise ValueError('Invalid sample target; never impute missing counts')
    dates = sorted({r['month'] for r in rows})
    if len(dates) < 10 or len({r['barangay_id'] for r in rows}) < 2:
        return {'status': 'not_fitted_insufficient_observed_sample', 'mae': None, 'rmse': None}
    train_dates, test_dates = dates[:-3], dates[-3:]
    split = sum(r['month'] in train_dates for r in rows)
    x, y = feature_frame(rows), np.asarray([r['count'] for r in rows], dtype=float)
    folds = []
    for end in [len(train_dates)-4, len(train_dates)-2]:
        train_set, val_set = set(train_dates[:end]), set(train_dates[end:end+2])
        a = np.array([i for i in range(split) if rows[i]['month'] in train_set])
        b = np.array([i for i in range(split) if rows[i]['month'] in val_set])
        if len(a) < 3 or not len(b):
            raise ValueError('Insufficient earlier-date validation rows')
        folds.append((a, b))
    preprocess = ColumnTransformer([('numeric', StandardScaler(), NUMERIC),
                                    ('area', OneHotEncoder(handle_unknown='ignore', sparse_output=False), ['barangay'])])
    estimator = Pipeline([('preprocess', preprocess), ('svr', CheckedSVR(kernel='rbf', max_iter=1000000))])
    search = GridSearchCV(estimator, GRID, cv=folds, scoring={'mae': 'neg_mean_absolute_error',
                          'rmse': 'neg_root_mean_squared_error'}, refit=False, n_jobs=1, error_score='raise')
    search.fit(x.iloc[:split], y[:split])
    index = min(range(len(search.cv_results_['params'])), key=lambda i: (
        -search.cv_results_['mean_test_mae'][i], -search.cv_results_['mean_test_rmse'][i], i))
    chosen = search.cv_results_['params'][index]
    model = estimator.set_params(**chosen).fit(x.iloc[:split], y[:split])
    out.mkdir(parents=True, exist_ok=False)
    joblib.dump(model, out/'evaluation-model.joblib')
    frozen = {'parameters': chosen, 'training_through': train_dates[-1], 'training_rows': split,
              'test_dates': test_dates, 'test_used_for_parameter_selection': False,
              'model_sha256': hashlib.sha256((out/'evaluation-model.joblib').read_bytes()).hexdigest()}
    write(out/'frozen-before-scoring.json', frozen)
    prediction = np.maximum(0., model.predict(x.iloc[split:]))
    # Both forecasts receive earlier observed test targets for later periods only.
    baseline = [float(x.iloc[i]['previous_observed']) if not x.iloc[i]['previous_missing'] else float(np.mean(y[:split]))
                for i in range(split, len(rows))]
    measured, previous = metrics(y[split:], prediction), metrics(y[split:], baseline)
    results = []
    for row, p, b in zip(rows[split:], prediction, baseline):
        results.append({'barangay': row['barangay'], 'barangay_id': row['barangay_id'], 'month': row['month'],
                        'actual_sample_count': row['count'], 'svr_prediction': float(p), 'previous_observed_baseline': float(b)})
    cv = [{'parameters': params, 'mae': -float(search.cv_results_['mean_test_mae'][i]),
           'rmse': -float(search.cv_results_['mean_test_rmse'][i])} for i, params in enumerate(search.cv_results_['params'])]
    write(out/'cv-results.json', cv)
    # Retained research artifact, never used to claim the test error of a fit that includes tests.
    refit = clone(model).fit(x, y)
    joblib.dump(refit, out/'all-observed-sample-refit.joblib')
    write(out/'research-refit.json', {'target': TARGET, 'rows': len(rows), 'trained_through': dates[-1],
        'deployment_status': 'Research artifact only; no operational future municipal prediction',
        'evaluated_with_this_refit': False, 'parameters': chosen})
    return {'status': 'fitted_experimental_actual_record_sample', 'target': TARGET, 'parameters': chosen,
            **measured, 'baseline': {'name': 'Previous observed count per barangay, same test rows', **previous},
            'training_rows': split, 'test_rows': len(rows)-split, 'training_through': train_dates[-1],
            'test_months': test_dates, 'sample_barangays': sorted({r['barangay'] for r in rows}),
            'earlier_cv_mae': cv[index]['mae'], 'earlier_cv_rmse': cv[index]['rmse'],
            'validation_folds': [{'training_through': rows[a[-1]]['month'], 'validation_start': rows[b[0]]['month'],
                                 'validation_end': rows[b[-1]]['month']} for a, b in folds],
            'test_predictions': results, 'municipal_migration_accuracy_claim': False,
            'operational_future_forecast_available': False}


def render_report(out, source, result, workbook):
    sample = result['recorded_arrival_sample_svr']
    esc = lambda v: html.escape(str(v))
    table = ''.join('<tr>'+''.join(f'<td>{esc(v)}</td>' for v in [r['barangay'], r['month'], r['actual_sample_count'],
        f"{r['svr_prediction']:.4f}", f"{r['previous_observed_baseline']:.4f}"] )+'</tr>' for r in sample.get('test_predictions', []))
    metrics_text = (f"SVR MAE <b>{sample['mae']:.4f}</b>; RMSE <b>{sample['rmse']:.4f}</b>. "
        f"Previous-observed baseline MAE <b>{sample['baseline']['mae']:.4f}</b>; RMSE <b>{sample['baseline']['rmse']:.4f}</b>. "
        f"{sample['training_rows']} training rows and {sample['test_rows']} test rows across {len(sample['test_months'])} dates.") if sample.get('mae') is not None else 'Not enough eligible sample rows to fit.'
    params = esc(json.dumps(sample.get('parameters', {})))
    diagnostic = result.get('additional_training_mean_baseline')
    diagnostic_text = '' if diagnostic is None else (f"<p><b>Additional diagnostic: frozen pooled training mean</b> MAE {diagnostic['mae']:.4f}, RMSE {diagnostic['rmse']:.4f}. "
        "This baseline was added after the initial run for a fuller comparison, without changing SVR settings or model files. "
        "The SVR does not beat this mean baseline on MAE. Improvement over one baseline is not evidence of general superiority.</p>")
    doc = f'''<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pooled actual-record SVR review</title><style>body{{font:16px/1.6 Arial,sans-serif;background:#eef3f7;color:#20394e;margin:0}}main{{max-width:1000px;margin:24px auto;padding:28px;background:white}}h1{{font-size:28px}}h2{{font-size:21px;margin-top:28px}}.notice{{background:#fff3dc;border:1px solid #dfc48a;padding:18px}}table{{border-collapse:collapse;width:100%}}th,td{{padding:10px;text-align:left;border-bottom:1px solid #d8e2eb}}.scroll{{overflow:auto}}code{{overflow-wrap:anywhere}}a{{color:#195b91}}@media(max-width:650px){{main{{padding:18px;margin:0}}}}</style><main>
<h1>Pooled actual-record SVR review</h1><p class="notice"><b>One experimental SVR model fitted to existing classified arrival records.</b> No validated municipal in/out model was fitted. The sample score below is not the accuracy of future physical municipal migration. No labels, dates or official source verification were invented.</p>
<h2>Source reconstruction</h2><p>{source['configured_barangays']} barangays reviewed; {workbook['workbooks_reviewed']} original workbooks, {workbook['sheets_reviewed']} worksheets scanned. Existing classified arrivals: {source['existing_classified_arrivals']}; unknown previous residence: {source['arrival_entries_with_unknown_origin']}. Unclassified dated entries: {source['unclassified_dated_entries']}. Away/transferred profiles without dated non-demo departures: {source['undated_departure_profiles']}. Non-demo dated ledger events: {len(source['non_demo_dated_events'])}.</p>
<p>Reconstruction retains explicit source reporting months and existing saved classifications. A monthly key does not invent an exact movement day. Missing reporting months remain unknown. Birth dates, import timestamps, school/occupation notes, current away status and spreadsheet header years never supply movement dates. Classification suggestions remain in a private review queue; no source rows or human attestations were changed.</p>
<h2>Actual municipal migration models</h2><table><thead><tr><th>Target</th><th>Fit status</th><th>MAE</th><th>RMSE</th></tr></thead><tbody><tr><td>External municipal in-migration</td><td>Not fitted: complete periods and origin boundaries unestablished</td><td>Unavailable</td><td>Unavailable</td></tr><tr><td>External municipal out-migration</td><td>Not fitted: only one dated non-demo event; no verified complete periods</td><td>Unavailable</td><td>Unavailable</td></tr></tbody></table>
<h2>Experimental pooled SVR: recorded classified arrivals</h2><p>{metrics_text}</p>{diagnostic_text}<p>Selected parameters: <code>{params}</code>. StandardScaler is fitted inside each earlier-date fold, with one-hot barangay encoding and an RBF SVR. Calendar sin/cos, previous observed count, a missing-history flag and elapsed months are inputs. Previous observed is not a fabricated lag for a missing calendar month. No current population or current residence label is used as a historical predictor.</p>
<p>The last three distinct observed dates are scored after selecting settings on two earlier expanding-date folds. All barangays on a date share the same partition. Earlier test actuals may enter later features after their own dates; the model stays frozen. This is a retrospective, previously inspected small sample, not a fresh untouched or prospective holdout.</p>
<div class="scroll"><table><thead><tr><th>Barangay</th><th>Month</th><th>Actual sample count</th><th>SVR</th><th>Previous observed</th></tr></thead><tbody>{table}</tbody></table></div>
<p>Coverage is only the barangays and observed source months shown in the data. Sample zeros mean that the classified records in that observed month were non-arrivals; they do not assert zero physical migration. Existing classifications, especially drafts, have not been independently verified. Unknown origins prevent aggregation into municipal external migration totals. No operational future prediction is issued from this historical sample.</p>
<h2>Defense explanation</h2><p>“We implemented a genuine pooled Support Vector Regressor using actual saved records, without synthetic training rows. Its experimental target is the count of existing classified arrival entries within eligible observed barangay source months. We selected parameters using earlier dates and reported errors on later dates. These scores describe a small retrospective administrative-record sample; they do not validate total municipal migration. The out-migration model remains untrained because dated and complete labels are insufficient.”</p>
<p><b>Cebuano:</b> “Tinuod nga SVR ang among gi-train, apan ang nasukod nga accuracy kay para sa existing classified arrival records sa observed months. Dili namo giangkon nga sakto na ang future municipal in/out migration. Wala mi nag-imbento og dates o missing-month zeros. Kinahanglan pa makumpirma ang origins, departure dates ug kompleto nga reporting periods.”</p>
<p>Software tests establish implementation behavior; they do not prove real-world accuracy. The saved all-sample refit is a research artifact, not the model used to score the held-out rows. Existing production forecasts and scenario assumptions remain unchanged. Earlier Tinago/dummy scores used different targets and dates and cannot be used as a like-for-like comparison here.</p>
<p><a href="reconstructed-source.json">Aggregate reconstructed source</a> · <a href="protocol.json">Protocol</a> · <a href="pooled-result.json">Exact results</a> · <a href="source-integrity.json">Source integrity</a></p></main></html>'''
    (out/'report.html').write_text(doc, encoding='utf-8')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('directory', type=Path)
    parser.add_argument('--workbook-review', type=Path, required=True)
    args = parser.parse_args()
    out = args.directory
    if (out/'pooled-result.json').exists():
        raise FileExistsError('Use a fresh source export')
    source_path = out/'reconstructed-source.json'
    source = json.loads(source_path.read_text(encoding='utf-8'))
    integrity = json.loads((out/'source-integrity.json').read_text(encoding='utf-8'))
    assert source['format'] == 'rbim.pooled_actual_review.v1'
    assert source['synthetic_rows'] == source['age_inferred_labels'] == 0 and integrity['source_tables_unchanged']
    workbook = json.loads(args.workbook_review.read_text(encoding='utf-8'))
    assert workbook['workbooks_reviewed'] == source['configured_barangays'] and not workbook['source_files_changed']
    write(out/'protocol.json', protocol())
    sample = train_sample(source, out/'sample-svr')
    result = {'source_sha256': hashlib.sha256(source_path.read_bytes()).hexdigest(),
        'actual_municipal_in_svr': {'status': 'not_fitted_no_complete_boundary_verified_labels', 'mae': None, 'rmse': None},
        'actual_municipal_out_svr': {'status': 'not_fitted_insufficient_dated_complete_labels', 'mae': None, 'rmse': None},
        'recorded_arrival_sample_svr': sample, 'source_records_changed': False, 'production_model_replaced': False,
        'synthetic_training_rows': 0, 'age_inferred_training_labels': 0}
    write(out/'pooled-result.json', result)
    write(out/'workbook-review-summary.json', {'workbooks_reviewed': workbook['workbooks_reviewed'], 'sheets_reviewed': workbook['sheets_reviewed'],
        'files': [{'file': w['file'], 'sha256': w['sha256'], 'dated_movement_text_matches': len(w['dated_movement_text']),
                   'movement_date_headers': len(w['movement_date_headers'])} for w in workbook['workbooks']]})
    render_report(out, source, result, workbook)
    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
