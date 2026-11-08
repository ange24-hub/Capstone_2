import json
from pathlib import Path
import tempfile
import unittest
import joblib
import numpy as np
from ml.retrain_actual_verified import train,features,latest_run


class ActualVerifiedRetrainTest(unittest.TestCase):
    def rows(self,n=28):
        return [{'month':f'{2022+i//12}-{i%12+1:02d}','count':(i*3)%8,'verified':True}for i in range(n)]

    def test_missing_or_unverified_counts_cannot_be_zero_imputed_or_fitted(self):
        self.assertEqual([],latest_run([]))
        for problem in ['unknown','unverified','negative','gap']:
            rows=self.rows(28)
            if problem=='unknown':rows[14]['count']=None
            if problem=='unverified':rows[14]['verified']=False
            if problem=='negative':rows[14]['count']=-1
            if problem=='gap':rows[14]['month']='2025-12'
            with tempfile.TemporaryDirectory()as directory:
                result=train(rows,Path(directory)/'model')
                self.assertIsNone(result['mae']);self.assertIsNone(result['rmse'])
                self.assertFalse((Path(directory)/'model').exists())

    def test_feature_inputs_do_not_see_their_own_or_future_target_counts(self):
        rows=self.rows();before=features(rows)
        rows[20]['count']=9000;rows[27]['count']=8000
        after=features(rows)
        np.testing.assert_array_equal(before[:21],after[:21])
        self.assertNotEqual(before[21,2],after[21,2])

    def test_final_test_never_selects_parameters_or_enters_its_own_forecast(self):
        rows=self.rows();changed=self.rows()
        for i in range(23,28):changed[i]['count']=1000+i
        with tempfile.TemporaryDirectory()as directory:
            root=Path(directory)
            before=train(rows,root/'before');after=train(changed,root/'after')
            self.assertEqual(before['parameters'],after['parameters'])
            self.assertEqual(before['test_predictions'][0]['predicted'],after['test_predictions'][0]['predicted'])
            model=joblib.load(root/'before/model.joblib')
            np.testing.assert_allclose(model.named_steps['scale'].mean_,features(rows)[:23].mean(axis=0))
            refit=joblib.load(root/'before/future_model.joblib')
            np.testing.assert_allclose(refit.named_steps['scale'].mean_,features(rows).mean(axis=0))
            e=np.array([r['actual']-r['predicted']for r in after['test_predictions']])
            self.assertAlmostEqual(float(np.abs(e).mean()),after['mae'])
            self.assertAlmostEqual(float(np.sqrt(np.square(e).mean())),after['rmse'])
            frozen=json.loads((root/'before/frozen-before-test.json').read_text())
            self.assertFalse(frozen['test_used_for_selection'])
            self.assertEqual('2023-11',frozen['training_through'])


if __name__=='__main__':unittest.main()
