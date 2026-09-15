from flask import Flask, request, jsonify
import joblib
import pandas as pd

app = Flask(__name__)

MODEL_PATH = "rbim_out_migration_model.joblib"

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
    data = request.get_json()

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

    input_data = pd.DataFrame([{
        feature: data[feature]
        for feature in FEATURES
    }])

    prediction = model.predict(input_data)[0]

    return jsonify({
        "predicted_next_month_out_migration": round(float(prediction), 2)
    })


if __name__ == "__main__":
    app.run(
        host="127.0.0.1",
        port=5001,
        debug=False
    )