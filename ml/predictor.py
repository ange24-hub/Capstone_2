from flask import Flask, request, jsonify
import joblib
import pandas as pd
from pathlib import Path
import math

app = Flask(__name__)

MODEL_PATH = Path(__file__).resolve().parent / "rbim_out_migration_model.joblib"

model = joblib.load(MODEL_PATH)

FEATURES = [
    "out_migration_count",
    "previous_month_out",
    "out_3month_avg",
    "year",
    "month"
]


@app.route("/health", methods=["GET"])
def health():
    return jsonify({
        "status": "online",
        "model": "RBIM Out-Migration Gradient Boosting"
    })


@app.route("/predict", methods=["POST"])
def predict():
    data = request.get_json(silent=True)

    if not isinstance(data, dict):
        return jsonify({"error": "Send a JSON object with migration features"}), 400

    missing = [
        feature
        for feature in FEATURES
        if feature not in data
    ]

    if missing:
        return jsonify({
            "error": "Missing required fields",
            "fields": missing
        }), 400

    values = {}
    for feature in FEATURES:
        value = data[feature]
        if isinstance(value, bool) or not isinstance(value, (int, float)) or not math.isfinite(value):
            return jsonify({"error": "Features must be finite numbers", "field": feature}), 400
        values[feature] = value

    if any(values[feature] < 0 for feature in FEATURES[:3]):
        return jsonify({"error": "Migration counts must be non-negative"}), 400
    if not (1900 <= values["year"] <= 9999 and values["year"] == int(values["year"])):
        return jsonify({"error": "Year must be an integer between 1900 and 9999"}), 400
    if not (1 <= values["month"] <= 12 and values["month"] == int(values["month"])):
        return jsonify({"error": "Month must be an integer between 1 and 12"}), 400

    input_data = pd.DataFrame([values], columns=FEATURES)

    try:
        prediction = float(model.predict(input_data)[0])
        if not math.isfinite(prediction):
            raise ValueError("Model returned a non-finite prediction")
    except Exception:
        app.logger.exception("Out-migration model could not produce an estimate")
        return jsonify({"error": "Prediction temporarily unavailable"}), 503

    return jsonify({
        "predicted_next_month_out_migration": max(0, round(prediction, 2))
    })


if __name__ == "__main__":
    app.run(
        host="127.0.0.1",
        port=5001,
        debug=False
    )
