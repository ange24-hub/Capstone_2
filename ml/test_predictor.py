import math
import unittest
from unittest.mock import patch

from ml.predictor import app, model


class PredictionApiTest(unittest.TestCase):
    def setUp(self):
        self.client = app.test_client()
        self.features = {
            "out_migration_count": 3,
            "previous_month_out": 2,
            "out_3month_avg": 2.33,
            "year": 2026,
            "month": 9,
        }

    def test_bundled_model_produces_a_finite_nonnegative_estimate(self):
        self.assertEqual(self.client.get("/health").get_json()["status"], "online")
        response = self.client.post("/predict", json=self.features)
        self.assertEqual(response.status_code, 200)
        prediction = response.get_json()["predicted_next_month_out_migration"]
        self.assertTrue(math.isfinite(prediction))
        self.assertGreaterEqual(prediction, 0)

    def test_missing_or_invalid_json_is_rejected(self):
        for payload in [None, [], "text", {}, {"month": 9}]:
            with self.subTest(payload=payload):
                self.assertEqual(self.client.post("/predict", json=payload).status_code, 400)
        self.assertEqual(self.client.post("/predict", data="invalid", content_type="application/json").status_code, 400)

    def test_invalid_feature_values_are_rejected(self):
        cases = [
            ("month", 0), ("month", 13), ("month", 1.5),
            ("year", 1899), ("year", 10000), ("year", 2026.5),
            ("out_migration_count", -1), ("previous_month_out", True),
            ("out_3month_avg", float("nan")), ("out_3month_avg", float("inf")),
            ("out_migration_count", "3"), ("out_migration_count", None),
        ]
        for feature, value in cases:
            with self.subTest(feature=feature, value=value):
                self.assertEqual(self.client.post("/predict", json={**self.features, feature: value}).status_code, 400)

    def test_model_failure_returns_unavailable_for_laravel_fallback(self):
        with patch.object(model, "predict", side_effect=ValueError("model failure")), patch.object(app.logger, "exception"):
            self.assertEqual(self.client.post("/predict", json=self.features).status_code, 503)
        with patch.object(model, "predict", return_value=[float("nan")]), patch.object(app.logger, "exception"):
            self.assertEqual(self.client.post("/predict", json=self.features).status_code, 503)
        with patch.object(model, "predict", return_value=[-2]):
            response = self.client.post("/predict", json=self.features)
            self.assertEqual(response.get_json()["predicted_next_month_out_migration"], 0)


if __name__ == "__main__":
    unittest.main()
