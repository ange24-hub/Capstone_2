"""SVR fitting with identical (scaled X, y) rows represented by total weight."""
import os
from pathlib import Path

import numpy as np
from sklearn.svm import SVR


class WeightedDuplicateSVR(SVR):
    def fit(self, X, y, sample_weight=None):
        x = np.asarray(X, dtype=float); y = np.asarray(y, dtype=float)
        pairs, inverse = np.unique(np.column_stack([x, y]), axis=0, return_inverse=True)
        weights = np.bincount(inverse, weights=None if sample_weight is None else np.asarray(sample_weight, dtype=float))
        self.original_training_rows_ = len(y)
        self.compressed_training_rows_ = len(weights)
        super().fit(pairs[:, :-1], pairs[:, -1], sample_weight=weights)
        # Optional per-process progress; never changes fitting or predictions.
        directory = os.environ.get('SVR_WIDE_PROGRESS_DIR')
        if directory:
            with (Path(directory)/f'{os.getpid()}.log').open('a', encoding='utf-8') as stream:
                stream.write(f'{len(y)},{len(weights)}\n')
        return self
