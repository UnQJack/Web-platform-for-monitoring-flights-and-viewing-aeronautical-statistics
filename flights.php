<?php
session_start();
require_once 'db.php';
require_once 'add_notification.php';
require_once 'send_email_notification.php';
require_once 'auto_update_flights.php';

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $isAdmin) {
    $flightId = (int)$_POST['flight_id'];
    $newStatus = $_POST['status'];

    $allowedStatuses = ['Active', 'Completed', 'Cancelled'];

    if ($flightId > 0 && in_array($newStatus, $allowedStatuses)) {
        $stmt = $conn->prepare("UPDATE flights SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $flightId);
        $stmt->execute();
        addNotification(
            $conn,
            'flights',
            'Actualizare',
            'Starea zborului cu ID #' . $flightId . ' a fost modificata in ' . $newStatus . '.'
        );
        $emailSql = "
            SELECT
                u.email,
                u.name,
                f.flight_number,
                f.status,
                f.scheduled_departure,
                f.scheduled_arrival,
                f.estimated_departure,
                f.estimated_arrival,
                a.name AS airline_name,
                ao.iata_code AS origin_code,
                ad.iata_code AS destination_code
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports ao ON f.origin_airport_id = ao.id
            JOIN airports ad ON f.destination_airport_id = ad.id
            WHERE f.id = ?
        ";

        $emailStmt = $conn->prepare($emailSql);
        $emailStmt->bind_param("i", $flightId);
        $emailStmt->execute();
        $emailResult = $emailStmt->get_result();

        $statusRo = $newStatus;
        
        if ($newStatus === 'Active') {
            $statusRo = 'Activ';
        } elseif ($newStatus === 'Completed') {
            $statusRo = 'Finalizat';
        } elseif ($newStatus === 'Cancelled') {
            $statusRo = 'Anulat';
        }

        while ($emailData = $emailResult->fetch_assoc()) {
            $subject = "SkyTix - Stare zbor actualizata";

            $message = "
                <h2>Starea zborului tau a fost actualizata</h2>
                <p>Buna, " . htmlspecialchars($emailData['name']) . "!</p>
                <p>Starea zborului asociat rezervarii tale a fost modificat.</p>
                <p><strong>Zbor:</strong> " . htmlspecialchars($emailData['flight_number']) . "</p>
                <p><strong>Companie:</strong> " . htmlspecialchars($emailData['airline_name']) . "</p>
                <p><strong>Ruta:</strong> " . htmlspecialchars($emailData['origin_code']) . " → " . htmlspecialchars($emailData['destination_code']) . "</p>
                <p><strong>Starea noua:</strong> " . htmlspecialchars($statusRo) . "</p>
                <p>Multumim ca folosesti SkyTix!</p>
            ";

            sendEmailNotification(
                $conn,
                $emailData['email'],
                $subject,
                $message
            );
        }

        $emailStmt->close();
        $stmt->close();
    }

    header("Location: flights.php");
    exit;
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_flight_id'])) {
    $deleteFlightId = (int)$_POST['delete_flight_id'];

    $stmt = $conn->prepare("DELETE FROM flights WHERE id = ?");
    $stmt->bind_param("i", $deleteFlightId);
    $stmt->execute();
    addNotification(
        $conn,
        'flights',
        'Stergere',
        'A fost sters zborul cu ID #' . $deleteFlightId . '.'
    );
    $stmt->close();

    header("Location: flights.php");
    exit;
}

$statsSql = "
    SELECT
        COUNT(*) AS total_flights,
        SUM(status = 'Active') AS active_flights,
        SUM(status = 'Completed') AS completed_flights,
        SUM(status = 'Cancelled') AS cancelled_flights
    FROM flights
";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

$flightsSql = "
    SELECT
        f.id,
        f.flight_number,
        f.callsign,
        f.status,
        f.scheduled_departure,
        f.scheduled_arrival,
        f.estimated_departure,
        f.estimated_arrival,
        f.actual_departure,
        f.actual_arrival,
        f.base_price,
        a.name AS airline_name,
        ac.model AS aircraft_model,
        ao.iata_code AS origin_code,
        ao.city AS origin_city,
        ad.iata_code AS destination_code,
        ad.city AS destination_city
    FROM flights f
    JOIN airlines a ON f.airline_id = a.id
    LEFT JOIN aircraft ac ON f.aircraft_id = ac.id
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id
    ORDER BY f.scheduled_departure ASC
";
$flightsResult = $conn->query($flightsSql);
$flights = [];

while ($row = $flightsResult->fetch_assoc()) {
    $flights[] = $row;
}

$airlinesList = [];
$airlinesResult = $conn->query("SELECT name FROM airlines ORDER BY name ASC");

while ($row = $airlinesResult->fetch_assoc()) {
    $airlinesList[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyTix - Zboruri</title>

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
            font-size: 24px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 20px;
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
            font-size: 40px;
            font-weight: 700;
        }

        .table-card {
            background: white;
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.04);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            gap: 20px;
        }

        .table-title {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .add-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #d8b75b;
            color: #1f1f1f;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .add-btn:hover {
            background: #cfaf52;
        }

        .filters-row {
            display: flex;
            gap: 14px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            width: 100%;
        }

        .filter-input,
        .filter-select {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 15px;
            outline: none;
        }

        .filter-input {
            min-width: 320px;
            flex: 1;
        }

        .filter-select {
            min-width: 180px;
        }

        .filter-input:focus,
        .filter-select:focus {
            border-color: #d8b75b;
            box-shadow: 0 0 0 2px rgba(216, 183, 91, 0.18);
        }

        .flights-table {
            width: 100%;
            border-collapse: collapse;
        }

        .flights-table th,
        .flights-table td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid #ececec;
            font-size: 15px;
            vertical-align: top;
        }

        .flights-table th {
            color: #8a8a8a;
            font-weight: 600;
            background: #fafafa;
        }

        .route {
            font-weight: 700;
        }

        .subtext {
            color: #8a8a8a;
            font-size: 13px;
            margin-top: 4px;
        }

        .status-form {
            margin: 0;
        }

        .status-select {
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            outline: none;
        }

        .status-select.active {
            background: #f5edd1;
            color: #7a5a1a;
        }

        .status-select.completed {
            background: #e4d09c;
            color: #1f1f1f;
        }

        .status-select.cancelled {
            background: #1f1f1f;
            color: #ffffff;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .status-badge.active {
            background: #f5edd1;
            color: #7a5a1a;
        }

        .status-badge.completed {
            background: #e4d09c;
            color: #1f1f1f;
        }

        .status-badge.cancelled {
            background: #1f1f1f;
            color: #ffffff;
        }

        .empty-text {
            color: #8b8b8b;
            font-size: 15px;
            margin-top: 10px;
        }

        @media (max-width: 1200px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .flights-table {
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

            .cards {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-input,
            .filter-select {
                width: 100%;
                min-width: 100%;
            }
        }

        .actions-cell {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        .edit-btn,
        .delete-btn {
            width: 120px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 800;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .edit-btn {
            background: #efefef;
            color: #1f1f1f;
        }

        .edit-btn:hover {
            background: #dddddd;
        }

        .delete-btn {
            border: none;
            background: #1f1f1f;
            color: #ffffff;
            cursor: pointer;
        }

        .delete-btn:hover {
            opacity: 0.9;
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
                <li class="active"><a href="flights.php">Zboruri</a></li>
                <li><a href="payments.php">Plati</a></li>
                <li><a href="messages.php">Mesaje</a></li>
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
            <div class="topbar">
                <h2>Zboruri</h2>
            </div>

            <section class="cards">
                <div class="card">
                    <small>Total Zboruri</small>
                    <div class="value"><?= (int)($stats['total_flights'] ?? 0) ?></div>
                </div>

                <div class="card">
                    <small>Zboruri Active</small>
                    <div class="value"><?= (int)($stats['active_flights'] ?? 0) ?></div>
                </div>

                <div class="card">
                    <small>Zboruri Finalizate</small>
                    <div class="value"><?= (int)($stats['completed_flights'] ?? 0) ?></div>
                </div>

                <div class="card">
                    <small>Zboruri Anulate</small>
                    <div class="value"><?= (int)($stats['cancelled_flights'] ?? 0) ?></div>
                </div>
            </section>

            <section class="table-card">
                <div class="table-header">
                    <div class="table-title">Lista Zborurilor</div>

                    <?php if ($isAdmin): ?>
                        <a href="add_flight.php" class="add-btn">Adauga</a>
                    <?php endif; ?>
                </div>

                <div class="filters-row">
                    <input
                        type="text"
                        id="flightSearch"
                        class="filter-input"
                        placeholder="Cauta zbor, companie, ruta..."
                    >

                    <select id="statusFilter" class="filter-select">
                        <option value="">Toate starile</option>
                        <option value="active">Activ</option>
                        <option value="completed">Finalizat</option>
                        <option value="cancelled">Anulat</option>
                    </select>

                    <select id="airlineFilter" class="filter-select">
                        <option value="">Toate companiile</option>

                        <?php foreach ($airlinesList as $airline): ?>
                            <option value="<?= strtolower(htmlspecialchars($airline['name'])) ?>">
                                <?= htmlspecialchars($airline['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($flights)): ?>
                    <table class="flights-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Zbor</th>
                                <th>Companie</th>
                                <th>Aeronava</th>
                                <th>Ruta</th>
                                <th>Pret</th>
                                <th>Plecare</th>
                                <th>Sosire</th>
                                <th>Stare</th>
                                <?php if ($isAdmin): ?>
                                    <th>Actiuni</th>
                                <?php endif; ?>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($flights as $flight): ?>
                                <tr
                                    data-search="<?= strtolower(htmlspecialchars(
                                        $flight['id'] . ' ' .
                                        $flight['flight_number'] . ' ' .
                                        $flight['callsign'] . ' ' .
                                        $flight['airline_name'] . ' ' .
                                        ($flight['aircraft_model'] ?? '') . ' ' .
                                        $flight['base_price'] . ' ' .
                                        $flight['origin_code'] . ' ' .
                                        $flight['destination_code'] . ' ' .
                                        $flight['origin_city'] . ' ' .
                                        $flight['destination_city']
                                    )) ?>"
                                    data-status="<?= strtolower(htmlspecialchars($flight['status'])) ?>"
                                    data-airline="<?= strtolower(htmlspecialchars($flight['airline_name'])) ?>"
                                >
                                    <td>#<?= (int)$flight['id'] ?></td>

                                    <td>
                                        <div><strong><?= htmlspecialchars($flight['flight_number']) ?></strong></div>
                                        <div class="subtext"><?= htmlspecialchars($flight['callsign']) ?></div>
                                    </td>

                                    <td><?= htmlspecialchars($flight['airline_name']) ?></td>

                                    <td><?= htmlspecialchars($flight['aircraft_model'] ?? '—') ?></td>

                                    <td>
                                        <div class="route">
                                            <?= htmlspecialchars($flight['origin_code']) ?> → <?= htmlspecialchars($flight['destination_code']) ?>
                                        </div>
                                        <div class="subtext">
                                            <?= htmlspecialchars($flight['origin_city']) ?> → <?= htmlspecialchars($flight['destination_city']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <strong><?= number_format((float)$flight['base_price'], 2) ?> RON</strong>
                                    </td>

                                    <td>
                                        <div><strong>Programat:</strong> <?= htmlspecialchars($flight['scheduled_departure']) ?></div>
                                        <div class="subtext">Estimat: <?= htmlspecialchars($flight['estimated_departure']) ?></div>
                                        <div class="subtext">Actual: <?= htmlspecialchars($flight['actual_departure']) ?></div>
                                    </td>

                                    <td>
                                        <div><strong>Programat:</strong> <?= htmlspecialchars($flight['scheduled_arrival']) ?></div>
                                        <div class="subtext">Estimat: <?= htmlspecialchars($flight['estimated_arrival']) ?></div>
                                        <div class="subtext">Actual: <?= htmlspecialchars($flight['actual_arrival']) ?></div>
                                    </td>

                                    <td>
                                        <?php if ($isAdmin): ?>
                                            <form method="POST" class="status-form">
                                                <input type="hidden" name="flight_id" value="<?= (int)$flight['id'] ?>">
                                                <input type="hidden" name="update_status" value="1">

                                                <select
                                                    name="status"
                                                    class="status-select <?= strtolower($flight['status']) ?>"
                                                    onchange="this.form.submit()"
                                                >
                                                    <option value="Active" <?= $flight['status'] === 'Active' ? 'selected' : '' ?>>Activ</option>
                                                    <option value="Completed" <?= $flight['status'] === 'Completed' ? 'selected' : '' ?>>Finalizat</option>
                                                    <option value="Cancelled" <?= $flight['status'] === 'Cancelled' ? 'selected' : '' ?>>Anulat</option>
                                                </select>
                                            </form>
                                        <?php else: ?>
                                            <?php
                                                $statusText = $flight['status'];

                                                if ($flight['status'] === 'Active') {
                                                    $statusText = 'Activ';
                                                } elseif ($flight['status'] === 'Completed') {
                                                    $statusText = 'Finalizat';
                                                } elseif ($flight['status'] === 'Cancelled') {
                                                    $statusText = 'Anulat';
                                                }
                                            ?>

                                            <span class="status-badge <?= strtolower($flight['status']) ?>">
                                                <?= $statusText ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <?php if ($isAdmin): ?>
                                        <td>
                                            <div class="actions-cell">
                                                <a href="edit_flight.php?id=<?= (int)$flight['id'] ?>" class="edit-btn">
                                                    Editare
                                                </a>
                                                <form method="POST" onsubmit="return confirm('Sigur vrei sa stergi acest zbor?');">
                                                    <input type="hidden" name="delete_flight_id" value="<?= (int)$flight['id'] ?>">
                                                    <button type="submit" class="delete-btn">Sterge</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-text">Nu exista inca zboruri in baza de date.</div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script>
const flightSearch = document.getElementById('flightSearch');
const statusFilter = document.getElementById('statusFilter');
const airlineFilter = document.getElementById('airlineFilter');

function filterFlights() {
    const searchValue = flightSearch.value.toLowerCase().trim();
    const statusValue = statusFilter.value;
    const airlineValue = airlineFilter.value;

    document.querySelectorAll('.flights-table tbody tr').forEach(row => {
        const rowSearch = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';
        const rowAirline = row.dataset.airline || '';

        const matchesSearch = rowSearch.includes(searchValue);
        const matchesStatus = statusValue === '' || rowStatus === statusValue;
        const matchesAirline = airlineValue === '' || rowAirline === airlineValue;

        row.style.display = matchesSearch && matchesStatus && matchesAirline ? '' : 'none';
    });
}

flightSearch.addEventListener('input', filterFlights);
statusFilter.addEventListener('change', filterFlights);
airlineFilter.addEventListener('change', filterFlights);

setInterval(() => {
    fetch('auto_update_flights.php')
        .then(() => location.reload());
}, 30000);
</script>

</body>
</html>