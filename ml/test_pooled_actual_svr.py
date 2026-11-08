import copy
import json
from pathlib import Path
import tempfile
import unittest
import joblib
import numpy as np
from ml.train_pooled_actual_svr import TARGET, NUMERIC, feature_frame, train_sample


class PooledActualSvrTest(unittest.TestCase):
    def rows(self):
        data = [('2020-07',1,0),('2020-09',1,0),('2020-11',1,0),('2020-12',1,4),
                ('2021-01',1,2),('2021-03',2,3),('2021-04',1,1),('2021-05',1,7),
                ('2021-05',2,2),('2021-06',1,1),('2021-06',2,0),('2021-09',2,2)]
        return [{'month': m, 'barangay_id': a, 'barangay': f'Fixture {a}', 'count': n, 'target': TARGET} for m,a,n in data]

    def test_gaps_do_not_create_rows_and_own_or_other_area_target_cannot_change_its_features(self):
        rows = self.rows(); x = feature_frame(rows)
        self.assertEqual(12, len(x))
        self.assertEqual(2, x.iloc[1]['gap_months'])
        self.assertEqual(0, x.iloc[1]['previous_missing'])
        mutated = copy.deepcopy(rows); mutated[7]['count'] += 1000
        other = feature_frame(mutated)
        np.testing.assert_allclose(x.iloc[:8][NUMERIC], other.iloc[:8][NUMERIC])
        np.testing.assert_allclose(x.iloc[8][NUMERIC].to_numpy(dtype=float), other.iloc[8][NUMERIC].to_numpy(dtype=float))
        self.assertNotEqual(x.iloc[9]['previous_observed'], other.iloc[9]['previous_observed'])

    def test_final_test_targets_cannot_select_settings_or_enter_training_scaler(self):
        rows = self.rows(); changed = copy.deepcopy(rows)
        for row in changed:
            if row['month'] >= '2021-05': row['count'] += 100
        with tempfile.TemporaryDirectory() as temp:
            a = Path(temp)/'original'; b = Path(temp)/'changed'
            result = train_sample({'experimental_arrival_samples': rows}, a)
            mutated = train_sample({'experimental_arrival_samples': changed}, b)
            self.assertEqual(result['parameters'], mutated['parameters'])
            self.assertEqual(result['earlier_cv_mae'], mutated['earlier_cv_mae'])
            self.assertAlmostEqual(result['test_predictions'][0]['svr_prediction'], mutated['test_predictions'][0]['svr_prediction'])
            self.assertEqual(['2021-05','2021-06','2021-09'], result['test_months'])
            model = joblib.load(a/'evaluation-model.joblib')
            np.testing.assert_allclose(model.named_steps['preprocess'].named_transformers_['numeric'].mean_, feature_frame(rows).iloc[:7][NUMERIC].mean())
            frozen = json.loads((a/'frozen-before-scoring.json').read_text())
            self.assertFalse(frozen['test_used_for_parameter_selection'])
            self.assertEqual('2021-04', frozen['training_through'])
            y = np.array([r['actual_sample_count'] for r in result['test_predictions']])
            p = np.array([r['svr_prediction'] for r in result['test_predictions']])
            self.assertAlmostEqual(float(np.mean(abs(y-p))), result['mae'])
            self.assertAlmostEqual(float(np.sqrt(np.mean((y-p)**2))), result['rmse'])
            self.assertFalse(result['operational_future_forecast_available'])
            self.assertFalse(result['municipal_migration_accuracy_claim'])

    def test_missing_targets_duplicates_and_wrong_targets_never_enter_training(self):
        for value in [None, -1, '2']:
            rows = self.rows(); rows[2]['count'] = value
            with tempfile.TemporaryDirectory() as temp, self.assertRaises(ValueError):
                train_sample({'experimental_arrival_samples': rows}, Path(temp)/'model')
        rows = self.rows(); rows.append(rows[0])
        with tempfile.TemporaryDirectory() as temp, self.assertRaises(ValueError):
            train_sample({'experimental_arrival_samples': rows}, Path(temp)/'model')
        rows = self.rows(); rows[0]['target'] = 'municipal_physical_migration'
        with tempfile.TemporaryDirectory() as temp, self.assertRaises(ValueError):
            train_sample({'experimental_arrival_samples': rows}, Path(temp)/'model')


if __name__ == '__main__':
    unittest.main()
