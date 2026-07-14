<?php
session_start();
require_once 'db.php';

$flightsSql = "
    SELECT
        f.id,
        f.flight_number,
        f.scheduled_departure,
        f.base_price,

        a.name AS airline_name,

        ao.iata_code AS origin_code,
        ao.lat AS origin_lat,
        ao.lon AS origin_lon,

        ad.iata_code AS destination_code,
        ad.lat AS destination_lat,
        ad.lon AS destination_lon,

        (
            SELECT COUNT(*)
            FROM bookings b
            WHERE b.flight_id = f.id
        ) AS bookings_count,

        COALESCE(t.signal_strength, -70) AS signal_strength,
        COALESCE(t.latency_ms, 120) AS latency_ms,
        COALESCE(t.packet_loss, 1.0) AS packet_loss

    FROM flights f

    JOIN airlines a
        ON f.airline_id = a.id

    JOIN airports ao
        ON f.origin_airport_id = ao.id

    JOIN airports ad
        ON f.destination_airport_id = ad.id

    LEFT JOIN (
        SELECT t1.*
        FROM telemetry t1
        JOIN (
            SELECT flight_id, MAX(recorded_at) AS max_time
            FROM telemetry
            GROUP BY flight_id
        ) t2
            ON t1.flight_id = t2.flight_id
           AND t1.recorded_at = t2.max_time
    ) t
        ON f.id = t.flight_id
    WHERE f.status = 'active'
    ORDER BY f.scheduled_departure ASC
";

$result = $conn->query($flightsSql);
$predictions = [];

function distanceKm($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

while ($flight = $result->fetch_assoc()) {
    $departureTimestamp = strtotime($flight['scheduled_departure']);

    $payload = [
        "airline" => $flight['airline_name'],
        "origin" => $flight['origin_code'],
        "destination" => $flight['destination_code'],
        "departure_hour" => (int)date("H", $departureTimestamp),
        "weekday" => (int)date("N", $departureTimestamp),
        "distance_km" => round(distanceKm(
            (float)$flight['origin_lat'],
            (float)$flight['origin_lon'],
            (float)$flight['destination_lat'],
            (float)$flight['destination_lon']
        ), 2),
        "base_price" => (float)$flight['base_price'],
        "bookings_count" => (int)$flight['bookings_count'],
        "signal_strength" => (int)$flight['signal_strength'],
        "latency_ms" => (int)$flight['latency_ms'],
        "packet_loss" => (float)$flight['packet_loss']
    ];

    $ch = curl_init("http://127.0.0.1:5050/predict_delay");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 5
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    $prediction = null;

    if ($response) {
        $prediction = json_decode($response, true);
        if ($prediction && ($prediction['status'] ?? '') === 'ok') {
    $probability = (float)$prediction['delay_probability'];

    if ($probability <= 1) {
        $probability *= 100;
    }

    $probability = round($probability, 1);
    $delayMinutes = round((float)$prediction['delay_minutes'], 1);
    $riskLevel = $prediction['risk_level'];

    $_SESSION['ai_predictions'][$flight['id']] = [
        'delay_probability' => $probability,
        'delay_minutes' => $delayMinutes,
        'risk_level' => $riskLevel,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $prediction['delay_probability'] = $probability;
    $prediction['delay_minutes'] = $delayMinutes;
}
    }

    $predictions[] = [
        "flight" => $flight,
        "payload" => $payload,
        "prediction" => $prediction,
        "error" => $curlError
    ];
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>SkyTix - AI Predictions</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {
    background: #f3efe4;
    color: #1f1f1f;
}

.page-wrapper {
    max-width: 1450px;
    margin: 40px auto;
    padding: 0 20px;
}

.dashboard-shell {
    background: #efeded;
    border-radius: 30px;
    display: grid;
    grid-template-columns: 240px 1fr;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.05);
}

.sidebar {
    background: #f7f7f7;
    padding: 28px 20px;
    min-height: 850px;
}

.sidebar-logo {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 30px;
}

.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 12px;
}

.menu li a {
    display: block;
    text-decoration: none;
    color: #5a5a5a;
    font-weight: 500;
    padding: 14px 16px;
    border-radius: 14px;
}

.menu li.active a {
    background: #e4d09c;
    color: #1f1f1f;
    font-weight: 700;
}

.main {
    padding: 26px;
}

.page-title {
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 22px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 22px;
}

.card {
    background: white;
    border-radius: 22px;
    padding: 22px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
}

.card small {
    color: #8a8a8a;
    display: block;
    margin-bottom: 8px;
}

.card .value {
    font-size: 34px;
    font-weight: 900;
}

.predictions-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
}

.prediction-table {
    width: 100%;
    border-collapse: collapse;
}

.prediction-table th,
.prediction-table td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #ececec;
    font-size: 15px;
    vertical-align: top;
}

.prediction-table th {
    color: #8a8a8a;
    background: #fafafa;
}

.subtext {
    color: #8a8a8a;
    font-size: 13px;
    margin-top: 4px;
}

.badge {
    display: inline-block;
    padding: 8px 13px;
    border-radius: 999px;
    font-weight: 800;
    font-size: 13px;
}

.risk-low {
    background: #e6f2df;
    color: #2f6b24;
}

.risk-medium {
    background: #f5edd1;
    color: #7a5a1a;
}

.risk-high {
    background: #1f1f1f;
    color: #ffffff;
}

.error {
    color: #8f2f2f;
    font-weight: 700;
}

@media (max-width: 1200px) {
    .cards {
        grid-template-columns: 1fr;
    }

    .prediction-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

@media (max-width: 850px) {
    .dashboard-shell {
        grid-template-columns: 1fr;
    }

    .sidebar {
        min-height: auto;
    }
}
</style>
</head>

<body>
<div class="page-wrapper">
    <div class="dashboard-shell">
        <aside class="sidebar">
            <div class="sidebar-logo">✈ SkyTix</div>
            <ul class="menu">
                <li><a href="home.php">Pagina Principala</a></li>
                <li><a href="bookings.php">Rezervari</a></li>
                <li><a href="flights.php">Zboruri</a></li>
                <li><a href="payments.php">Plati</a></li>
                <li><a href="messages.php">Mesaje</a></li>
                <li><a href="tracking.php">Urmarire Zboruri</a></li>
                <li><a href="telecom.php">Telecom</a></li>
                <li><a href="radar.php">Radar</a></li>
                <li class="active"><a href="ai_predictions.php">Predictii AI</a></li>
                <li><a href="deals.php">Oferte</a></li>
                <li><a href="anomaly_detection.php">Detectare Anomalii</a></li>
                <li><a href="alerts.php">Alerte</a></li>
            </ul>
        </aside>

        <main class="main">
            <div class="page-title">Predictii AI</div>

            <section class="cards">
                <div class="card">
                    <small>Zboruri analizate</small>
                    <div class="value"><?= count($predictions) ?></div>
                </div>

                <div class="card">
                    <small>Model ML</small>
                    <div class="value">Random Forest</div>
                </div>

                <div class="card">
                    <small>Date folosite</small>
                    <div class="value">Zboruri + Telecom</div>
                </div>
            </section>

            <section class="predictions-card">
                <table class="prediction-table">
                    <thead>
                        <tr>
                            <th>Zbor</th>
                            <th>Ruta</th>
                            <th>Date de intrare</th>
                            <th>Probabilitate de intarziere</th>
                            <th>Intarziere estimata</th>
                            <th>Risc</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($predictions as $item): ?>
                        <?php
                        $flight = $item['flight'];
                        $payload = $item['payload'];
                        $prediction = $item['prediction'];

                        $riskClass = 'risk-low';

                        if ($prediction && isset($prediction['risk_level'])) {
                            if ($prediction['risk_level'] === 'Mediu') {
                                $riskClass = 'risk-medium';
                            } elseif ($prediction['risk_level'] === 'Ridicat') {
                                $riskClass = 'risk-high';
                            }
                        }
                        ?>

                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($flight['flight_number']) ?></strong>
                                <div class="subtext"><?= htmlspecialchars($flight['airline_name']) ?></div>
                            </td>

                            <td>
                                <strong><?= htmlspecialchars($flight['origin_code']) ?> → <?= htmlspecialchars($flight['destination_code']) ?></strong>
                                <div class="subtext"><?= round($payload['distance_km']) ?> km</div>
                            </td>

                            <td>
                                <div>Ora: <?= date("H:i", strtotime($flight['scheduled_departure'])) ?></div>
                                <div class="subtext">Rezervari: <?= $payload['bookings_count'] ?></div>
                                <div class="subtext">Semnal: <?= $payload['signal_strength'] ?> dBm</div>
                                <div class="subtext">Latenta: <?= $payload['latency_ms'] ?> ms</div>
                                <div class="subtext">Pierderea de Pachete: <?= $payload['packet_loss'] ?>%</div>
                            </td>

                            <?php if ($prediction && ($prediction['status'] ?? '') === 'ok'): ?>
                                <td>
                                    <strong><?= htmlspecialchars($prediction['delay_probability']) ?>%</strong>
                                </td>

                                <td>
                                    <strong>+<?= htmlspecialchars($prediction['delay_minutes']) ?> min</strong>
                                </td>

                                <td>
                                    <span class="badge <?= $riskClass ?>">
                                        <?= htmlspecialchars($prediction['risk_level']) ?>
                                    </span>
                                </td>
                            <?php else: ?>
                                <td colspan="3" class="error">
                                    API ML indisponibil.
                                    <?php if (!empty($item['error'])): ?>
                                        <?= htmlspecialchars($item['error']) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</div>
<script>
setInterval(() => {
    location.reload();
}, 5000);
</script>
</body>
</html>