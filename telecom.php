<?php
session_start();
require_once 'db.php';

$sql = "
    SELECT
        t.id,
        t.flight_id,
        t.recorded_at,
        t.signal_strength,
        t.latency_ms,
        t.packet_loss,
        t.frequency_band,
        t.connection_status,
        f.flight_number,
        a.name AS airline_name,
        ao.iata_code AS origin_code,
        ad.iata_code AS destination_code
    FROM telemetry t
    JOIN flights f ON t.flight_id = f.id
    JOIN airlines a ON f.airline_id = a.id
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id
    ORDER BY t.recorded_at DESC
";

$result = $conn->query($sql);
$telemetry = [];

while ($row = $result->fetch_assoc()) {
    $telemetry[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>SkyTix - Telecom</title>

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

.telecom-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
}

.telecom-table {
    width: 100%;
    border-collapse: collapse;
}

.telecom-table th,
.telecom-table td {
    text-align: left;
    padding: 14px 12px;
    border-bottom: 1px solid #ececec;
    font-size: 15px;
}

.telecom-table th {
    color: #8a8a8a;
    background: #fafafa;
}

.badge {
    padding: 8px 12px;
    border-radius: 999px;
    font-weight: 800;
    font-size: 13px;
}

.stabil {
    background: #e6f2df;
    color: #2f6b24;
}

.instabil {
    background: #f5edd1;
    color: #7a5a1a;
}

.deconectat {
    background: #1f1f1f;
    color: white;
}

.empty {
    color: #8a8a8a;
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
                <li class="active"><a href="telecom.php">Telecom</a></li>
                <li><a href="radar.php">Radar</a></li>
                <li><a href="ai_predictions.php">Predictii AI</a></li>
                <li><a href="deals.php">Oferte</a></li>
                <li><a href="anomaly_detection.php">Detectare Anomalii</a></li>
                <li><a href="alerts.php">Alerte</a></li>
            </ul>
        </aside>

        <main class="main">
            <div class="page-title">Telecom</div>

            <div class="telecom-card">
                <?php if (!empty($telemetry)): ?>
                    <table class="telecom-table">
                        <thead>
                            <tr>
                                <th>Zbor</th>
                                <th>Ruta</th>
                                <th>Semnal</th>
                                <th>Latenta</th>
                                <th>Pierdere Pachete</th>
                                <th>Banda</th>
                                <th>Stare Conexiune</th>
                                <th>Actualizare</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($telemetry as $row): ?>
                                <?php
                                    $status = $row['connection_status'];

                                    $statusClass = 'instabil';
                                    $statusText = 'Instabil';

                                    if ($status === 'Stabil') {
                                        $statusClass = 'stabil';
                                        $statusText = 'Stabil';
                                    }
                                    elseif ($status === 'Instabil') {
                                        $statusClass = 'instabil';
                                        $statusText = 'Instabil';
                                    }
                                    elseif ($status === 'Deconectat') {
                                        $statusClass = 'deconectat';
                                        $statusText = 'Deconectat';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['flight_number']) ?></strong><br>
                                        <?= htmlspecialchars($row['airline_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['origin_code']) ?>
                                        →
                                        <?= htmlspecialchars($row['destination_code']) ?>
                                    </td>

                                    <td><?= (int)$row['signal_strength'] ?> dBm</td>
                                    <td><?= (int)$row['latency_ms'] ?> ms</td>
                                    <td><?= number_format((float)$row['packet_loss'], 2) ?>%</td>
                                    <td><?= htmlspecialchars($row['frequency_band']) ?></td>

                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>

                                    <td><?= htmlspecialchars($row['recorded_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">Nu exista date telecom inca.</div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
</body>
<script>
setInterval(() => {
    location.reload();
}, 3000);
</script>
</html>