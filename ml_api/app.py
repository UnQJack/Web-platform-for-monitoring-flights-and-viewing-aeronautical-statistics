from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import pandas as pd
import numpy as np
from tensorflow.keras.models import load_model

app = Flask(__name__)
CORS(app)

delay_model = joblib.load("delay_model.pkl")
risk_model = joblib.load("risk_model.pkl")

anomaly_model = load_model("anomaly_autoencoder.keras")
anomaly_scaler = joblib.load("anomaly_scaler.pkl")
anomaly_threshold = joblib.load("anomaly_threshold.pkl")

@app.route("/predict_delay", methods=["POST"])
def predict_delay():
    data = request.get_json()

    required_fields = [
        "airline",
        "origin",
        "destination",
        "departure_hour",
        "weekday",
        "distance_km",
        "base_price",
        "bookings_count",
        "signal_strength",
        "latency_ms",
        "packet_loss"
    ]

    for field in required_fields:
        if field not in data:
            return jsonify({
                "status": "error",
                "message": f"Lipseste campul {field}"
            }), 400

    df = pd.DataFrame([data])

    delay_minutes = delay_model.predict(df)[0]
    delayed_probability = risk_model.predict_proba(df)[0][1]

    actual_delay_minutes = data.get("actual_delay_minutes", None)

    prediction_anomaly = False
    prediction_error = None

    if actual_delay_minutes is not None:
        prediction_error = abs(float(actual_delay_minutes) - float(delay_minutes))

        if prediction_error >= 30:
            prediction_anomaly = True

    if delayed_probability >= 0.65:
        risk_level = "Ridicat"
    elif delayed_probability >= 0.35:
        risk_level = "Mediu"
    else:
        risk_level = "Scazut"

    return jsonify({
        "status": "ok",
        "delay_minutes": round(float(delay_minutes), 1),
        "delay_probability": round(float(delayed_probability) * 100, 1),
        "risk_level": risk_level,
        "actual_delay_minutes": actual_delay_minutes,
        "prediction_error": round(float(prediction_error), 1) if prediction_error is not None else None,
        "prediction_anomaly": prediction_anomaly
    })

@app.route("/detect_anomaly", methods=["POST"])
def detect_anomaly():
    data = request.get_json()

    required_fields = [
        "signal_strength",
        "latency_ms",
        "packet_loss",
        "altitude",
        "speed"
    ]

    for field in required_fields:
        if field not in data:
            return jsonify({
                "status": "error",
                "message": f"Lipseste campul {field}"
            }), 400

    df = pd.DataFrame([{
        "signal_strength": data["signal_strength"],
        "latency_ms": data["latency_ms"],
        "packet_loss": data["packet_loss"],
        "altitude": data["altitude"],
        "speed": data["speed"]
    }])

    X_scaled = anomaly_scaler.transform(df)
    reconstruction = anomaly_model.predict(X_scaled)
    mse = np.mean(np.power(X_scaled - reconstruction, 2), axis=1)[0]

    is_anomaly = mse > anomaly_threshold

    if is_anomaly:
        level = "Anomalie detectata"
    else:
        level = "Normal"

    return jsonify({
        "status": "ok",
        "reconstruction_error": round(float(mse), 5),
        "threshold": round(float(anomaly_threshold), 5),
        "is_anomaly": bool(is_anomaly),
        "level": level
    })

if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5050, debug=True)