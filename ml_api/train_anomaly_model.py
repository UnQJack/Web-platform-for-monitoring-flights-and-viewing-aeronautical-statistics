import numpy as np
import pandas as pd
import joblib
from sklearn.preprocessing import StandardScaler
from tensorflow.keras.models import Model
from tensorflow.keras.layers import Input, Dense
from tensorflow.keras.callbacks import EarlyStopping

np.random.seed(42)

normal_data = pd.DataFrame({
    "signal_strength": np.random.normal(-68, 8, 500),
    "latency_ms": np.random.normal(110, 30, 500),
    "packet_loss": np.random.normal(1.0, 0.5, 500),
    "altitude": np.random.normal(9500, 1800, 500),
    "speed": np.random.normal(780, 120, 500)
})

normal_data["packet_loss"] = normal_data["packet_loss"].clip(0, 5)
normal_data["altitude"] = normal_data["altitude"].clip(0, 12000)
normal_data["speed"] = normal_data["speed"].clip(0, 950)

features = [
    "signal_strength",
    "latency_ms",
    "packet_loss",
    "altitude",
    "speed"
]

X = normal_data[features]

scaler = StandardScaler()
X_scaled = scaler.fit_transform(X)

input_layer = Input(shape=(len(features),))
encoded = Dense(8, activation="relu")(input_layer)
encoded = Dense(4, activation="relu")(encoded)
decoded = Dense(8, activation="relu")(encoded)
decoded = Dense(len(features), activation="linear")(decoded)

autoencoder = Model(input_layer, decoded)
autoencoder.compile(optimizer="adam", loss="mse")

early_stop = EarlyStopping(
    monitor="loss",
    patience=10,
    restore_best_weights=True
)

autoencoder.fit(
    X_scaled,
    X_scaled,
    epochs=100,
    batch_size=32,
    shuffle=True,
    callbacks=[early_stop],
    verbose=1
)

reconstructions = autoencoder.predict(X_scaled)
mse = np.mean(np.power(X_scaled - reconstructions, 2), axis=1)

threshold = np.percentile(mse, 95)

autoencoder.save("anomaly_autoencoder.keras")
joblib.dump(scaler, "anomaly_scaler.pkl")
joblib.dump(threshold, "anomaly_threshold.pkl")

print("Model anomaly detection salvat.")
print("Threshold:", threshold)