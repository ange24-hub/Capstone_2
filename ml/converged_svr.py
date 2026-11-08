"""Exclude iteration-limited SVR fits rather than ranking unfinished solutions."""
import json
import os
from pathlib import Path
import warnings

from sklearn.exceptions import ConvergenceWarning
from sklearn.svm import SVR
from ml.weighted_duplicate_svr import WeightedDuplicateSVR


def checked_fit(model, function, X, y, sample_weight):
    status = 'error'
    try:
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', ConvergenceWarning)
            function(X, y, sample_weight=sample_weight)
        status = 'ok' if model.fit_status_ == 0 else 'not_converged'
        if status != 'ok':
            raise ValueError('SVR reached the predeclared iteration limit; exclude this fit.')
        return model
    finally:
        directory = os.environ.get('SVR_POLY_PROGRESS_DIR')
        if directory:
            record = {'status': status, 'rows': len(y), 'iterations': int(getattr(model, 'n_iter_', 0)),
                      'stage': type(model).__name__}
            with (Path(directory)/f'{os.getpid()}.jsonl').open('a', encoding='utf-8') as stream:
                stream.write(json.dumps(record)+'\n')


class CheckedDuplicateSVR(WeightedDuplicateSVR):
    def fit(self, X, y, sample_weight=None):
        return checked_fit(self, super().fit, X, y, sample_weight)


class CheckedSVR(SVR):
    def fit(self, X, y, sample_weight=None):
        return checked_fit(self, super().fit, X, y, sample_weight)
