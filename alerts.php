<?php
session_start();
require_once 'db.php';

$sql = "
    SELECT 
        al.id,
        al.flight_id,
        al.priority,
        al.score,
        al.message,
        al.created_at,

        f.flight_number,
        f.status,
        f.scheduled_departure,
        f.estimated_arrival,

        a.name AS airline_name,

        ao.iata_code AS origin_code,
        ao.city AS origin_city,

        ad.iata_code AS destination_code,
        ad.city AS destination_city

    FROM alerts al
    JOIN flights f ON al.flight_id = f.id
    JOIN airlines a ON f.airline_id = a.id
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id

    ORDER BY 
        CASE al.priority
            WHEN 'CRITICAL' THEN 4
            WHEN 'HIGH' THEN 3
            WHEN 'MEDIUM' THEN 2
            WHEN 'LOW' THEN 1
            ELSE 0
        END DESC,
        al.score DESC,
        al.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

$alerts = [];

while ($row = $result->fetch_assoc()) {
    $alerts[] = $row;
}

$stmt->close();

$totalAlerts = count($alerts);
$criticalAlerts = 0;
$highAlerts = 0;
$mediumAlerts = 0;
$lowAlerts = 0;

foreach ($alerts as $alert) {
    if ($alert['priority'] === 'CRITICAL') {
        $criticalAlerts++;
    } elseif ($alert['priority'] === 'HIGH') {
        $highAlerts++;
    } elseif ($alert['priority'] === 'MEDIUM') {
        $mediumAlerts++;
    } elseif ($alert['priority'] === 'LOW') {
        $lowAlerts++;
    }
}

function getPriorityClass($priority) {
    return match ($priority) {
        'CRITICAL' => 'critical',
        'HIGH' => 'high',
        'MEDIUM' => 'medium',
        'LOW' => 'low',
        default => 'info'
    };
}

function getPriorityText($priority) {
    return match ($priority) {
        'CRITICAL' => 'Critica',
        'HIGH' => 'Ridicata',
        'MEDIUM' => 'Medie',
        'LOW' => 'Scazuta',
        default => 'Informativa'
    };
}

function getStatusText($status) {
    return match ($status) {
        'Active' => 'Activ',
        'Completed' => 'Finalizat',
        'Cancelled' => 'Anulat',
        default => $status
    };
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>SkyTix - Alerte</title>

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

.menu li a:hover {
    background: #e8e8e8;
}

.menu li.active a {
    background: #e4d09c;
    color: #1f1f1f;
    font-weight: 700;
}

.main {
    padding: 26px;
}

.topbar {
    margin-bottom: 24px;
}

.topbar h2 {
    font-size: 28px;
    font-weight: 900;
    margin-bottom: 6px;
}

.topbar p {
    color: #8a8a8a;
    font-size: 15px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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
    font-size: 14px;
}

.card .value {
    font-size: 38px;
    font-weight: 900;
}

.alerts-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
}

.section-title {
    font-size: 26px;
    font-weight: 900;
    margin-bottom: 18px;
}

.alert-item {
    border: 1px solid #ececec;
    border-radius: 18px;
    padding: 18px;
    margin-bottom: 16px;
    background: #fafafa;
}

.alert-header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    margin-bottom: 12px;
}

.flight-title {
    font-size: 20px;
    font-weight: 900;
}

.flight-subtitle {
    color: #8a8a8a;
    font-size: 14px;
    margin-top: 4px;
}

.priority-badge {
    display: inline-block;
    padding: 9px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 900;
    white-space: nowrap;
}

.priority-badge.critical {
    background: #1f1f1f;
    color: #ffffff;
}

.priority-badge.high {
    background: #e4d09c;
    color: #1f1f1f;
}

.priority-badge.medium {
    background: #f5edd1;
    color: #7a5a1a;
}

.priority-badge.low {
    background: #e6f2df;
    color: #2f6b24;
}

.priority-badge.info {
    background: #efefef;
    color: #1f1f1f;
}

.alert-message {
    color: #444;
    line-height: 1.5;
    margin-bottom: 14px;
}

.alert-meta {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    border-top: 1px solid #ececec;
    padding-top: 14px;
}

.meta-box span {
    display: block;
    color: #8a8a8a;
    font-size: 13px;
    margin-bottom: 4px;
}

.meta-box strong {
    font-size: 15px;
}

.score {
    font-weight: 900;
}

.empty-text {
    color: #8a8a8a;
    font-size: 15px;
    padding: 18px;
    background: #fafafa;
    border-radius: 16px;
}

@media (max-width: 1200px) {
    .cards {
        grid-template-columns: repeat(2, 1fr);
    }

    .alert-meta {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 850px) {
    .dashboard-shell {
        grid-template-columns: 1fr;
    }

    .sidebar {
        min-height: auto;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .alert-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .alert-meta {
        grid-template-columns: 1fr;
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
                <li><a href="anomaly_detection.php">Anomalii</a></li>
                <li class="active"><a href="alerts.php">Alerte</a></li>
            </ul>
        </aside>

        <main class="main">
            <div class="topbar">
                <h2>Alerte</h2>
            </div>

            <section class="cards">
                <div class="card">
                    <small>Total alerte</small>
                    <div class="value"><?= (int)$totalAlerts ?></div>
                </div>

                <div class="card">
                    <small>Alerte critice</small>
                    <div class="value"><?= (int)$criticalAlerts ?></div>
                </div>

                <div class="card">
                    <small>Prioritate ridicata</small>
                    <div class="value"><?= (int)$highAlerts ?></div>
                </div>

                <div class="card">
                    <small>Prioritate medie/scazuta</small>
                    <div class="value"><?= (int)($mediumAlerts + $lowAlerts) ?></div>
                </div>
            </section>

            <section class="alerts-card">
                <div class="section-title">Lista alertelor</div>

                <?php if (empty($alerts)): ?>
                    <div class="empty-text">
                        Nu exista alerte generate momentan.
                    </div>
                <?php else: ?>

                    <?php foreach ($alerts as $alert): ?>
                        <?php
                            $priorityClass = getPriorityClass($alert['priority']);
                            $priorityText = getPriorityText($alert['priority']);
                            $statusText = getStatusText($alert['status']);
                        ?>

                        <div class="alert-item">
                            <div class="alert-header">
                                <div>
                                    <div class="flight-title">
                                        Zbor <?= htmlspecialchars($alert['flight_number']) ?>
                                    </div>

                                    <div class="flight-subtitle">
                                        <?= htmlspecialchars($alert['origin_city']) ?>
                                        (<?= htmlspecialchars($alert['origin_code']) ?>)
                                        →
                                        <?= htmlspecialchars($alert['destination_city']) ?>
                                        (<?= htmlspecialchars($alert['destination_code']) ?>)
                                        ·
                                        <?= htmlspecialchars($alert['airline_name']) ?>
                                    </div>
                                </div>

                                <span class="priority-badge <?= $priorityClass ?>">
                                    <?= htmlspecialchars($priorityText) ?>
                                </span>
                            </div>

                            <div class="alert-message">
                                <?= htmlspecialchars($alert['message']) ?>
                            </div>

                            <div class="alert-meta">
                                <div class="meta-box">
                                    <span>Stare zbor</span>
                                    <strong><?= htmlspecialchars($statusText) ?></strong>
                                </div>

                                <div class="meta-box">
                                    <span>Scor risc</span>
                                    <strong class="score"><?= htmlspecialchars($alert['score']) ?></strong>
                                </div>

                                <div class="meta-box">
                                    <span>Prioritate</span>
                                    <strong><?= htmlspecialchars($priorityText) ?></strong>
                                </div>

                                <div class="meta-box">
                                    <span>Generata la</span>
                                    <strong><?= htmlspecialchars($alert['created_at']) ?></strong>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>
            </section>
        </main>

    </div>
</div>

<script>
setInterval(() => {
    fetch('generate_alerts.php?ajax=1')
        .then(response => response.json())
        .then(data => {
            console.log('Alerte actualizate:', data);
            location.reload();
        })
        .catch(error => {
            console.error('Eroare actualizare alerte:', error);
        });
}, 15000);
</script>

</body>
</html>