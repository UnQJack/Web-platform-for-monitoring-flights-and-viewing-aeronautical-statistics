<?php
session_start();
require_once 'db.php';

$sql = "
    SELECT
        n.id,
        n.table_name,
        n.action_type,
        n.message,
        n.created_at,
        u.name AS user_name
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
";

$result = $conn->query($sql);
$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>SkyTix - Mesaje</title>

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
    margin-bottom: 24px;
}

.notifications-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
}

.notification-item {
    display: flex;
    gap: 16px;
    padding: 18px 0;
    border-bottom: 1px solid #ececec;
}

.notification-item:last-child {
    border-bottom: none;
}

.icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: #e4d09c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 800;
    margin-bottom: 6px;
}

.notification-message {
    color: #5a5a5a;
    margin-bottom: 6px;
}

.notification-meta {
    color: #8a8a8a;
    font-size: 13px;
}

.empty-text {
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
                <li class="active"><a href="messages.php">Mesaje</a></li>
                <li><a href="tracking.php">Urmarire Zboruri</a></li>
                <li><a href="telecom.php">Telecom</a></li>
                <li><a href="radar.php">Radar</a></li>
                <li><a href="ai_predictions.php">Predictii AI</a></li>
                <li><a href="deals.php">Oferte</a></li>
                <li><a href="anomaly_detection.php">Detectare Anomalii</a></li>
                <li><a href="alerts.php">Alerte</a></li>
            </ul>
        </aside>

        <main class="main">
            <div class="page-title">Mesaje</div>
            
            <div class="notifications-card">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item">
                            <div class="icon">!</div>

                            <div class="notification-content">
                                <div class="notification-title">
                                    <?= htmlspecialchars($notification['action_type']) ?>
                                    in tabela
                                    <?= htmlspecialchars($notification['table_name']) ?>
                                </div>

                                <div class="notification-message">
                                    <?= htmlspecialchars($notification['message']) ?>
                                </div>

                                <div class="notification-meta">
                                    Utilizator:
                                    <?= htmlspecialchars($notification['user_name'] ?? 'Sistem') ?>
                                    ·
                                    <?= htmlspecialchars($notification['created_at']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-text">Nu exista notificari momentan.</div>
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