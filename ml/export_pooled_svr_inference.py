"""Export the trusted local all-sample refit for numeric PHP inference. No fitting or DB access."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import sys

if __package__ in (None, ''):
    sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import joblib
import numpy as np
import pandas as pd
from ml.train_pooled_actual_svr import NUMERIC, TARGET, feature_frame


def export(directory, output):
    source_bytes = (directory/'reconstructed-source.json').read_bytes()
    source = json.loads(source_bytes)
    result = json.loads((directory/'pooled-result.json').read_text())
    checks = json.loads((directory/'independent-verification.json').read_text())
    if not checks['passed'] or hashlib.sha256(source_bytes).hexdigest() != result['source_sha256']:
        raise ValueError('Source verification mismatch')
    model_path = directory/'sample-svr/all-observed-sample-refit.joblib'
    # Only the trusted estimator created by this project's previous local training run is loaded.
    model = joblib.load(model_path)
    rows = sorted(source['experimental_arrival_samples'], key=lambda r: (r['month'], r['barangay_id']))
    if any(r['target'] != TARGET for r in rows):
        raise ValueError('Target mismatch')
    scale = model.named_steps['preprocess'].named_transformers_['numeric']
    encoder = model.named_steps['preprocess'].named_transformers_['area']
    svr = model.named_steps['svr']
    if svr.kernel != 'rbf' or svr.fit_status_ != 0 or int(scale.n_samples_seen_) != len(rows):
        raise ValueError('Expected a converged all-sample RBF refit')
    x = feature_frame(rows)
    if not np.allclose(scale.mean_, x[NUMERIC].mean()):
        raise ValueError('All-sample scaling mismatch')
    for key, value in result['recorded_arrival_sample_svr']['parameters'].items():
        if model.get_params()[key] != value:
            raise ValueError('Refit parameter mismatch')
    areas = []
    for area in sorted({r['barangay_id'] for r in rows}):
        last = [r for r in rows if r['barangay_id'] == area][-1]
        areas.append({'id': area, 'name': last['barangay'], 'last_observed_month': last['month'], 'last_observed_count': last['count']})
    categories = [str(v) for v in encoder.categories_[0]]
    if categories != sorted(str(a['id']) for a in areas):
        raise ValueError('Area encoding mismatch')
    artifact = {'format': 'rbim.pooled_recorded_arrivals_rbf_svr.v1', 'run': directory.name, 'target': TARGET,
        'exported_at': datetime.now(timezone.utc).isoformat(), 'source_sha256': result['source_sha256'],
        'source_fingerprint': source['source_fingerprint'],
        'refit_model_sha256': hashlib.sha256(model_path.read_bytes()).hexdigest(),
        'numeric_features': NUMERIC, 'area_categories': categories, 'areas': areas,
        'training': {'rows': len(rows), 'start': rows[0]['month'], 'end': rows[-1]['month'],
                     'parameters': result['recorded_arrival_sample_svr']['parameters'],
                     'max_observed_gap_months': float(x['gap_months'].max())},
        'scaler': {'mean': scale.mean_.tolist(), 'scale': scale.scale_.tolist()},
        'svr': {'gamma': float(svr._gamma), 'intercept': float(svr.intercept_[0]),
                'support_vectors': svr.support_vectors_.tolist(), 'dual_coefficients': svr.dual_coef_[0].tolist()},
        'historical_evaluation': {'mae': result['recorded_arrival_sample_svr']['mae'],
                                'rmse': result['recorded_arrival_sample_svr']['rmse'], 'test_rows': 5,
                                'applies_to_future_forecast_accuracy': False},
        'future_accuracy': {'mae': None, 'rmse': None},
        'inference_contract': 'Fixed observed inputs, no recursive predictions or gap filling; conditional on no intervening eligible observations. Covered barangays only.',
        'parity_cases': []}
    # Reference cases cover near-history and distant future inputs, including year rollover.
    for month in ['2021-10', '2021-11', '2021-12', '2026-12', '2027-01', '2027-02']:
        date = datetime.strptime(month, '%Y-%m')
        for area in areas:
            previous = datetime.strptime(area['last_observed_month'], '%Y-%m')
            numeric = [float(np.sin(2*np.pi*date.month/12)), float(np.cos(2*np.pi*date.month/12)),
                       float(area['last_observed_count']), 0., float((date.year-previous.year)*12+date.month-previous.month)]
            frame = pd.DataFrame([dict(zip(NUMERIC, numeric), barangay=str(area['id']))])
            reference = float(model.predict(frame)[0])
            transformed = [(v-m)/s for v,m,s in zip(numeric, artifact['scaler']['mean'], artifact['scaler']['scale'])]
            transformed += [float(str(area['id']) == value) for value in categories]
            independent = artifact['svr']['intercept'] + sum(
                coef*np.exp(-artifact['svr']['gamma']*sum((a-b)**2 for a,b in zip(transformed, support)))
                for coef,support in zip(artifact['svr']['dual_coefficients'], artifact['svr']['support_vectors']))
            if abs(independent-reference) > 1e-10:
                raise ValueError('Numeric inference parity failed')
            artifact['parity_cases'].append({'month': month, 'area_id': area['id'], 'raw_prediction': reference})
    output.parent.mkdir(parents=True, exist_ok=True)
    if output.exists():
        raise FileExistsError('Use a fresh export; do not overwrite an activated artifact')
    output.write_text(json.dumps(artifact, indent=2, allow_nan=False), encoding='utf-8')
    print(json.dumps({'output': str(output), 'rows': len(rows), 'areas': areas, 'parity_cases': len(artifact['parity_cases']),
                      'december_2026': artifact['parity_cases'][6:8]}, indent=2))
    return artifact


if __name__ == '__main__':
    p = argparse.ArgumentParser(); p.add_argument('directory', type=Path); p.add_argument('output', type=Path)
    a = p.parse_args(); export(a.directory, a.output)
