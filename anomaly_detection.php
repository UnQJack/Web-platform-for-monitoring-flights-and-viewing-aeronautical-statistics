<?php
session_start();
require_once 'db.php';

$sql = "
    SELECT
        f.id,
        f.flight_number,
        a.name AS airline_name,
        ao.iata_code AS origin_code,
        ad.iata_code AS destination_code,

        COALESCE(t.signal_strength, -70) AS signal_strength,
        COALESCE(t.latency_ms, 120) AS latency_ms,
        COALESCE(t.packet_loss, 1.0) AS packet_loss,

        COALESCE(p.altitude, 0) AS altitude,
        COALESCE(p.speed, 0) AS speed

    FROM flights f
    JOIN airlines a ON f.airline_id = a.id
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id

    LEFT JOIN (
        SELECT t1.*
        FROM telemetry t1
        JOIN (
            SELECT flight_id, MAX(recorded_at) AS max_time
            FROM telemetry
            GROUP BY flight_id
        ) t2 ON t1.flight_id = t2.flight_id 
             AND t1.recorded_at = t2.max_time
    ) t ON f.id = t.flight_id

    LEFT JOIN (
        SELECT p1.*
        FROM positions p1
        JOIN (
            SELECT flight_id, MAX(recorded_at) AS max_time
            FROM positions
            GROUP BY flight_id
        ) p2 ON p1.flight_id = p2.flight_id 
             AND p1.recorded_at = p2.max_time
    ) p ON f.id = p.flight_id
    
    WHERE f.status = 'Active'
     

    ORDER BY f.id ASC
";

$result = $conn->query($sql);
$rows = [];

while ($flight = $result->fetch_assoc()) {
    $payload = [
        "signal_strength" => (int)$flight['signal_strength'],
        "latency_ms" => (int)$flight['latency_ms'],
        "packet_loss" => (float)$flight['packet_loss'],
        "altitude" => (int)$flight['altitude'],
        "speed" => (int)$flight['speed']
    ];

    $ch = curl_init("http://127.0.0.1:5050/detect_anomaly");

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
    $error = curl_error($ch);
    curl_close($ch);

    $prediction = $response ? json_decode($response, true) : null;

    $rows[] = [
        "flight" => $flight,
        "prediction" => $prediction,
        "error" => $error
    ];
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>SkyTix - Detectare Anomalii</title>

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

.table-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
}

.anomaly-table {
    width: 100%;
    border-collapse: collapse;
}

.anomaly-table th,
.anomaly-table td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #ececec;
    font-size: 15px;
    vertical-align: top;
}

.anomaly-table th {
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

.normal {
    background: #e6f2df;
    color: #2f6b24;
}

.anomaly {
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

    .anomaly-table {
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
                <li><a href="ai_predictions.php">Predictii AI</a></li>
                <li><a href="deals.php">Oferte</a></li>
                <li class="active"><a href="anomaly_detection.php">Detectare Anomalii</a></li>
                <li><a href="alerts.php">Alerte</a></li>
            </ul>
        </aside>

        <main class="main">
            <div class="page-title">Detectare Anomalii</div>

            <?php
            $total = count($rows);
            $anomalies = 0;

            foreach ($rows as $r) {
                if (($r['prediction']['is_anomaly'] ?? false) === true) {
                    $anomalies++;
                }
            }

            $normal = $total - $anomalies;
            ?>

            <section class="cards">
                <div class="card">
                    <small>Zboruri analizate</small>
                    <div class="value"><?= $total ?></div>
                </div>

                <div class="card">
                    <small>Zboruri normale</small>
                    <div class="value"><?= $normal ?></div>
                </div>

                <div class="card">
                    <small>Anomalii detectate</small>
                    <div class="value"><?= $anomalies ?></div>
                </div>
            </section>

            <section class="table-card">
                <table class="anomaly-table">
                    <thead>
                        <tr>
                            <th>Zbor</th>
                            <th>Ruta</th>
                            <th>Parametri analizati</th>
                            <th>Eroare reconstructie</th>
                            <th>Prag</th>
                            <th>Stare</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($rows as $item): ?>
                        <?php
                        $flight = $item['flight'];
                        $prediction = $item['prediction'];
                        $isAnomaly = $prediction['is_anomaly'] ?? false;
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($flight['flight_number']) ?></strong>
                                <div class="subtext"><?= htmlspecialchars($flight['airline_name']) ?></div>
                            </td>

                            <td>
                                <strong><?= htmlspecialchars($flight['origin_code']) ?> → <?= htmlspecialchars($flight['destination_code']) ?></strong>
                            </td>

                            <td>
                                <div>Semnal: <?= (int)$flight['signal_strength'] ?> dBm</div>
                                <div class="subtext">Latenta: <?= (int)$flight['latency_ms'] ?> ms</div>
                                <div class="subtext">Pierdere de pachete: <?= (float)$flight['packet_loss'] ?>%</div>
                                <div class="subtext">Altitudine: <?= (int)$flight['altitude'] ?> m</div>
                                <div class="subtext">Viteza: <?= (int)$flight['speed'] ?> km/h</div>
                            </td>

                            <?php if ($prediction && ($prediction['status'] ?? '') === 'ok'): ?>
                                <td><?= htmlspecialchars($prediction['reconstruction_error']) ?></td>
                                <td><?= htmlspecialchars($prediction['threshold']) ?></td>

                                <td>
                                    <?php if ($isAnomaly): ?>
                                        <span class="badge anomaly">Anomalie</span>
                                    <?php else: ?>
                                        <span class="badge normal">Normal</span>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td colspan="3" class="error">
                                    API indisponibil. Porneste Flask pe portul 5050.
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