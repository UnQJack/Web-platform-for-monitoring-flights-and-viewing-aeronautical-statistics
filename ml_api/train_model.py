import pandas as pd
from sklearn.ensemble import RandomForestRegressor, RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.pipeline import Pipeline
from sklearn.compose import ColumnTransformer
from sklearn.preprocessing import OneHotEncoder
from sklearn.metrics import mean_absolute_error
import joblib

data = pd.DataFrame([
    ["Wizz Air", "OTP", "MLA", 10, 2, 1900, 199.99, 35, -70, 120, 1.2, 15, 1],
    ["Ryanair", "LHR", "MAD", 14, 4, 1250, 250.00, 42, -75, 140, 1.8, 25, 1],
    ["TAROM", "OTP", "CDG", 9, 1, 1850, 300.00, 20, -60, 80, 0.4, 5, 0],
    ["Qatar Airways", "DOH", "AMS", 18, 5, 4900, 950.00, 60, -82, 210, 3.1, 40, 1],
    ["Emirates", "DXB", "AUH", 12, 3, 120, 150.00, 15, -58, 70, 0.2, 0, 0],
    ["Turkish Airlines", "IST", "FCO", 16, 6, 1400, 360.00, 50, -73, 160, 2.0, 22, 1],
    ["HiSky", "FCO", "IST", 19, 7, 1400, 320.00, 30, -68, 115, 0.9, 10, 0],
    ["flydubai", "DXB", "AUH", 22, 5, 120, 180.00, 45, -85, 250, 4.5, 35, 1],
    ["TAROM", "OTP", "RMO", 8, 2, 350, 100.00, 18, -62, 90, 0.5, 3, 0],
    ["Wizz Air", "OTP", "MAD", 6, 1, 2450, 220.00, 55, -78, 180, 2.5, 30, 1],
], columns=[
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
    "packet_loss",
    "delay_minutes",
    "is_delayed"
])

X = data.drop(columns=["delay_minutes", "is_delayed"])
y_delay = data["delay_minutes"]
y_class = data["is_delayed"]

categorical = ["airline", "origin", "destination"]
numeric = [
    "departure_hour",
    "weekday",
    "distance_km",
    "base_price",
    "bookings_count",
    "signal_strength",
    "latency_ms",
    "packet_loss"
]

preprocessor = ColumnTransformer(
    transformers=[
        ("cat", OneHotEncoder(handle_unknown="ignore"), categorical),
        ("num", "passthrough", numeric)
    ]
)

delay_model = Pipeline([
    ("preprocessor", preprocessor),
    ("model", RandomForestRegressor(n_estimators=100, random_state=42))
])

risk_model = Pipeline([
    ("preprocessor", preprocessor),
    ("model", RandomForestClassifier(n_estimators=100, random_state=42))
])

delay_model.fit(X, y_delay)
risk_model.fit(X, y_class)

joblib.dump(delay_model, "delay_model.pkl")
joblib.dump(risk_model, "risk_model.pkl")

print("Modelele au fost salvate.")