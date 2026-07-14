<?php
require_once 'db.php';
session_start();

$statsSql = "
    SELECT
        SUM(status = 'Completed') AS completed_flights,
        SUM(status = 'Active') AS active_flights,
        SUM(status = 'Cancelled') AS canceled_flights
    FROM flights
";
$statsStmt = $conn->query($statsSql);
$stats = $statsStmt->fetch_assoc();

$totalFlights = (int)$stats['completed_flights'] + (int)$stats['active_flights'] + (int)$stats['canceled_flights'];

$completedPercent = $totalFlights > 0 ? round(($stats['completed_flights'] / $totalFlights) * 100, 2) : 0;
$activePercent = $totalFlights > 0 ? round(($stats['active_flights'] / $totalFlights) * 100, 2) : 0;
$canceledPercent = $totalFlights > 0 ? round(($stats['canceled_flights'] / $totalFlights) * 100, 2) : 0;

$revenueSql = "
    SELECT COALESCE(SUM(price), 0) AS total_revenue
    FROM bookings
    WHERE status = 'Paid'
";
$revenueStmt = $conn->query($revenueSql);
$revenue = $revenueStmt->fetch_assoc();

$popularAirlinesSql = "
    SELECT 
        a.name,
        COUNT(b.id) AS paid_bookings
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    JOIN airlines a ON f.airline_id = a.id
    WHERE b.status = 'Paid'
    GROUP BY a.id, a.name
    ORDER BY paid_bookings DESC
";
$popularStmt = $conn->query($popularAirlinesSql);
$popularAirlines = [];

while ($row = $popularStmt->fetch_assoc()) {
    $popularAirlines[] = $row;
}

$totalPaidBookingsByAirlines = 0;

foreach ($popularAirlines as $row) {
    $totalPaidBookingsByAirlines += (int)$row['paid_bookings'];
}

$chartLabels = [];
$chartValues = [];

foreach ($popularAirlines as $airline) {
    $chartLabels[] = $airline['name'];
    $chartValues[] = (int)$airline['paid_bookings'];
}

$topRoutesSql = "
    SELECT 
        ao.iata_code AS origin_code,
        ad.iata_code AS destination_code,
        COUNT(f.id) AS total_flights
    FROM flights f
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id
    GROUP BY f.origin_airport_id, f.destination_airport_id, ao.iata_code, ad.iata_code
    ORDER BY total_flights DESC
";
$topRoutesStmt = $conn->query($topRoutesSql);
$topRoutes = [];

while ($row = $topRoutesStmt->fetch_assoc()) {
    $topRoutes[] = $row;
}

$ticketSalesSql = "
    SELECT 
        DAYOFWEEK(created_at) AS day_index,
        COUNT(id) AS tickets_sold
    FROM bookings
    WHERE status = 'Paid'
    GROUP BY DAYOFWEEK(created_at)
    ORDER BY DAYOFWEEK(created_at)
";
$ticketSalesStmt = $conn->query($ticketSalesSql);

$ticketSalesMap = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];

while ($row = $ticketSalesStmt->fetch_assoc()) {
    $ticketSalesMap[(int)$row['day_index']] = (int)$row['tickets_sold'];
}

$ticketSalesLabels = ['Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sa', 'Du'];
$ticketSalesValues = [
    $ticketSalesMap[2],
    $ticketSalesMap[3],
    $ticketSalesMap[4],
    $ticketSalesMap[5],
    $ticketSalesMap[6],
    $ticketSalesMap[7],
    $ticketSalesMap[1]
];

$totalTicketsThisWeek = array_sum($ticketSalesValues);
$maxTicketsForChart = max(max($ticketSalesValues), 10);

$ticketRemainderValues = [];
foreach ($ticketSalesValues as $value) {
    $ticketRemainderValues[] = $maxTicketsForChart - $value;
}

$highlightDayIndex = array_search(max($ticketSalesValues), $ticketSalesValues);

$flightScheduleSql = "
    SELECT 
        MONTH(f.scheduled_departure) AS month_num,
        SUM(CASE WHEN ao.country = ad.country THEN 1 ELSE 0 END) AS domestic_count,
        SUM(CASE WHEN ao.country <> ad.country THEN 1 ELSE 0 END) AS international_count
    FROM flights f
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id
    WHERE f.status IN ('Active', 'Completed')
    GROUP BY MONTH(f.scheduled_departure)
    ORDER BY MONTH(f.scheduled_departure)
";
$flightScheduleStmt = $conn->query($flightScheduleSql);

$monthMap = [
    1 => ['label' => 'Ian', 'domestic' => 0, 'international' => 0],
    2 => ['label' => 'Feb', 'domestic' => 0, 'international' => 0],
    3 => ['label' => 'Mar', 'domestic' => 0, 'international' => 0],
    4 => ['label' => 'Apr', 'domestic' => 0, 'international' => 0],
    5 => ['label' => 'Mai', 'domestic' => 0, 'international' => 0],
    6 => ['label' => 'Iun', 'domestic' => 0, 'international' => 0],
    7 => ['label' => 'Iul', 'domestic' => 0, 'international' => 0],
    8 => ['label' => 'Aug', 'domestic' => 0, 'international' => 0],
    9 => ['label' => 'Sep', 'domestic' => 0, 'international' => 0],
    10 => ['label' => 'Oct', 'domestic' => 0, 'international' => 0],
    11 => ['label' => 'Noi', 'domestic' => 0, 'international' => 0],
    12 => ['label' => 'Dec', 'domestic' => 0, 'international' => 0],
];

while ($row = $flightScheduleStmt->fetch_assoc()) {
    $m = (int)$row['month_num'];
    if (isset($monthMap[$m])) {
        $monthMap[$m]['domestic'] = (int)$row['domestic_count'];
        $monthMap[$m]['international'] = (int)$row['international_count'];
    }
}

$flightScheduleLabels = [];
$domesticSeries = [];
$internationalSeries = [];
$totalFlightsPerMonth = [];

foreach ($monthMap as $month) {
    $flightScheduleLabels[] = $month['label'];
    $domesticSeries[] = $month['domestic'];
    $internationalSeries[] = $month['international'];
    $totalFlightsPerMonth[] = $month['domestic'] + $month['international'];
}

$topDestSql = "
    SELECT 
        a.city,
        a.iata_code,
        COUNT(b.id) AS total_bookings
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    JOIN airports a ON f.destination_airport_id = a.id
    WHERE b.status = 'Paid'
    GROUP BY a.id, a.city, a.iata_code
    ORDER BY total_bookings DESC
";
$topDestStmt = $conn->query($topDestSql);
$topDestinations = [];

while ($row = $topDestStmt->fetch_assoc()) {
    $topDestinations[] = $row;
}

$colors = ['#d8b75b', '#5f5f5f', '#7a5a1a', '#898989', '#966019', '#121212', '#f0d47a', '#787878', '#e4d09c', '#8b8b8b', '#a88d3f', '#2f2f2f'];
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skytix Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .topbar h2 {
            font-size: 24px;
        }

        .search {
            background: white;
            border: none;
            border-radius: 14px;
            padding: 12px 16px;
            width: 280px;
            outline: none;
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

        .revenue {
            font-size: 34px !important;
        }

        .trend-badge {
            display: inline-block;
            margin-top: 14px;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .trend-badge.light {
            background: #eedb9e;
            color: #1f1f1f;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            align-items: start;
        }

        .left-column,
        .right-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .airlines-card .airline-list,
        .top-destinations-card .airline-list {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 21px;
        }

        .airlines-card .airline-row,
        .top-destinations-card .airline-row {
            padding: 8px 0;
        }

        .widget-title {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .widget-subtitle {
            color: #8a8a8a;
            margin-bottom: 16px;
        }

        .airline-list {
            margin-top: 20px;
        }

        .airline-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            font-size: 16px;
        }

        .airline-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .color-dot {
            width: 22px;
            height: 8px;
            border-radius: 999px;
        }

        .routes-card {
            padding: 22px 24px;
        }

        .routes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .routes-table tr {
            border-bottom: 1px solid #ececec;
        }

        .routes-table td {
            padding: 14px 0;
            font-size: 16px;
        }

        .routes-table td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .empty-text {
            color: #8b8b8b;
            font-size: 15px;
            margin-top: 10px;
        }

        .ticket-sales-card {
            padding: 26px;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .ticket-total {
            font-size: 34px;
            font-weight: 800;
            color: #1f1f1f;
            margin-top: 8px;
        }

        .ticket-total span {
            font-size: 15px;
            font-weight: 500;
            color: #9a9a9a;
            margin-left: 8px;
        }

        .ticket-filter {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #d8b75b;
            color: #1f1f1f;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
        }

        .ticket-chart-wrap {
            position: relative;
            width: 100%;
            height: 320px;
            margin-top: 20px;
        }

        .schedule-card {
            padding: 26px;
        }

        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .schedule-legend {
            display: flex;
            gap: 28px;
            margin-top: 10px;
            color: #8a8a8a;
            font-size: 15px;
            font-weight: 500;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .legend-line {
            display: inline-block;
            width: 22px;
            height: 6px;
            border-radius: 999px;
        }

        .legend-line.domestic {
            background: #d8b75b;
        }

        .legend-line.international {
            background: #1f1f1f;
        }

        .schedule-filter {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #d8b75b;
            color: #1f1f1f;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
        }

        .schedule-select {
            border: none;
            background: transparent;
            color: #1f1f1f;
            font-weight: 600;
            font-size: 15px;
            outline: none;
            cursor: pointer;
        }

        .schedule-chart-wrap {
            position: relative;
            width: 100%;
            height: 340px;
            margin-top: 8px;
        }

        @media (max-width: 1200px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
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

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .search {
                width: 100%;
            }
        }
        .sidebar {
            background: #f7f7f7;
            padding: 28px 20px;
            min-height: 850px;
            display: flex;
            flex-direction: column;
        }

        .menu {
            list-style: none;
            flex-grow: 1;
        }

        .sidebar-auth {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .auth-btn {
            display: block;
            text-decoration: none;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 500;
        }

        .auth-btn:hover {
            text-decoration: none;
        }

        .login-btn {
            background: #d8b75b;
            color: #1f1f1f;
        }

        .login-btn:hover {
            background: #cfaf52;
        }

        .logout-btn {
            background: #1f1f1f;
            color: #ffffff;
        }

        .logout-btn:hover {
            background: #111111;
            color: #ffffff;
        }

        .user-badge {
            display: block;
            text-decoration: none;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 500;
        }

        .admin-badge {
            background: #e4d09c;
            color: #1f1f1f;
        }

        .user-badge-style {
            background: #ececec;
            color: #1f1f1f;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="dashboard-shell">
            <aside class="sidebar">
                <div class="sidebar-logo">✈ SkyTix</div>
                <ul class="menu">
                    <li class="active"><a href="home.php">Pagina Principala</a></li>
                    <li><a href="bookings.php">Rezervari</a></li>
                    <li><a href="flights.php">Zboruri</a></li>
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
                <div class="sidebar-auth">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="user-badge <?= $_SESSION['role'] === 'admin' ? 'admin-badge' : 'user-badge-style' ?>">
                            <?= $_SESSION['role'] === 'admin' ? 'Administrator' : 'Utilizator' ?>:<?= htmlspecialchars($_SESSION['user_name']) ?>
                        </div>

                        <a href="logout.php" class="auth-btn logout-btn">Deconectare</a>
                    <?php else: ?>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php" class="auth-btn login-btn">Autentificare</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <main class="main">
                <div class="topbar">
                    <h2>Pagina Principala</h2>
                </div>

                <section class="cards">
                    <div class="card">
                        <small>Zboruri Finalizate</small>
                        <div class="value"><?= (int)($stats['completed_flights'] ?? 0) ?></div>
                        <div class="trend-badge light"><?= $completedPercent ?>%</div>
                    </div>
                    <div class="card">
                        <small>Zboruri Active</small>
                        <div class="value"><?= (int)($stats['active_flights'] ?? 0) ?></div>
                        <div class="trend-badge light"><?= $activePercent ?>%</div>
                    </div>
                    <div class="card">
                        <small>Zboruri Anulate</small>
                        <div class="value"><?= (int)($stats['canceled_flights'] ?? 0) ?></div>
                        <div class="trend-badge light"><?= $canceledPercent ?>%</div>
                    </div>
                    <div class="card">
                        <small>Venit Total</small>
                        <div class="value revenue"><?= number_format((float)($revenue['total_revenue'] ?? 0), 2) ?> RON</div>
                    </div>
                </section>

                <div class="dashboard-grid">
                    <div class="left-column">

                        <div class="card routes-card">
                            <div class="widget-title">Cele mai bune rute de zbor</div>
                            <div class="widget-subtitle">Rutele cele mai frecvente</div>
                            <?php if (!empty($topRoutes)): ?>
                                <table class="routes-table">
                                    <tbody>
                                        <?php foreach ($topRoutes as $route): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($route['origin_code']) ?> → <?= htmlspecialchars($route['destination_code']) ?></td>
                                                <td><?= (int)$route['total_flights'] ?> zboruri</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-text">Nu exista inca date pentru rute.</div>
                            <?php endif; ?>
                        </div>

                        <div class="card ticket-sales-card">
                            <div class="ticket-header">
                                <div>
                                    <div class="widget-title">Vanzari de Bilete</div>
                                    <div class="ticket-total">
                                        <?= number_format($totalTicketsThisWeek) ?>
                                        <span>Bilete Vandute</span>
                                    </div>
                                </div>
                                <div class="ticket-filter">
                                    <span>Toate Rezervarile Platite</span>
                                </div>
                            </div>
                            <div class="ticket-chart-wrap">
                                <canvas id="ticketSalesChart"></canvas>
                            </div>
                        </div>

                        <div class="card schedule-card">
                            <div class="schedule-header">
                                <div>
                                    <div class="widget-title">Zboruri Programate</div>
                                    <div class="schedule-legend">
                                        <span class="legend-item">
                                            <span class="legend-line domestic"></span>
                                            Domestic
                                        </span>
                                        <span class="legend-item">
                                            <span class="legend-line international"></span>
                                            International
                                        </span>
                                    </div>
                                </div>
                                <div class="schedule-filter">
                                    <select id="scheduleRangeSelect" class="schedule-select">
                                        <option value="6">Ultimele 6 Luni</option>
                                        <option value="8" selected>Ultimele 8 Luni</option>
                                        <option value="12">Ultimele 12 Luni</option>
                                    </select>
                                </div>
                            </div>
                            <div class="schedule-chart-wrap">
                                <canvas id="flightScheduleChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="right-column">

                        <div class="card airlines-card">
                            <div class="widget-title">Companii Aeriene Populare</div>
                            <?php if (!empty($popularAirlines)): ?>
                                <canvas id="popularAirlinesChart" height="220"></canvas>
                                <div class="airline-list">
                                    <?php foreach ($popularAirlines as $index => $airline): ?>
                                        <?php $percentage = $totalPaidBookingsByAirlines > 0 ? (($airline['paid_bookings'] / $totalPaidBookingsByAirlines) * 100) : 0; ?>
                                        <div class="airline-row">
                                            <div class="airline-left">
                                                <span class="color-dot" style="background: <?= $colors[$index % count($colors)] ?>;"></span>
                                                <span><?= htmlspecialchars($airline['name']) ?></span>
                                            </div>
                                            <strong><?= number_format($percentage, 2) ?>%</strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-text">Nu exista inca rezervari cu status <strong>Paid</strong>.</div>
                            <?php endif; ?>
                        </div>

                        <div class="card top-destinations-card">
                            <div class="widget-title">Destinatii de Top</div>
                            <div class="widget-subtitle">Cele mai populare destinatii</div>
                            <?php if (!empty($topDestinations)): ?>
                                <div class="airline-list">
                                    <?php foreach ($topDestinations as $dest): ?>
                                        <div class="airline-row">
                                            <div class="airline-left">
                                                <span class="color-dot" style="background: #d8b75b;"></span>
                                                <span><?= htmlspecialchars($dest['city']) ?> (<?= htmlspecialchars($dest['iata_code']) ?>)</span>
                                            </div>
                                            <strong><?= (int)$dest['total_bookings'] ?> bilete</strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-text">Nu exista inca date pentru destinatii.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php if (!empty($popularAirlines)): ?>
        <script>
            const ctx = document.getElementById('popularAirlinesChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chartLabels) ?>,
                    datasets: [{
                        data: <?= json_encode($chartValues) ?>,
                        backgroundColor: <?= json_encode(array_slice($colors, 0, count($chartValues))) ?>,
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    cutout: '62%'
                }
            });
        </script>
    <?php endif; ?>

    <script>
        const ticketCtx = document.getElementById('ticketSalesChart').getContext('2d');
        const ticketLabels = <?= json_encode($ticketSalesLabels) ?>;
        const ticketValues = <?= json_encode($ticketSalesValues) ?>;
        const ticketRemainderValues = <?= json_encode($ticketRemainderValues) ?>;
        const highlightDayIndex = <?= json_encode($highlightDayIndex) ?>;

        const soldColors = ticketLabels.map((_, index) =>
            index === highlightDayIndex ? '#d8b75b' : '#1f1f1f'
        );

        new Chart(ticketCtx, {
            type: 'bar',
            data: {
                labels: ticketLabels,
                datasets: [
                    {
                        label: 'Tickets Sold',
                        data: ticketValues,
                        backgroundColor: soldColors,
                        borderRadius: { topLeft: 0, topRight: 0, bottomLeft: 14, bottomRight: 14 },
                        borderSkipped: false,
                        stack: 'sales',
                        barThickness: 48
                    },
                    {
                        label: 'Remaining',
                        data: ticketRemainderValues,
                        backgroundColor: '#e4e4e4',
                        borderRadius: { topLeft: 14, topRight: 14, bottomLeft: 0, bottomRight: 0 },
                        borderSkipped: false,
                        stack: 'sales',
                        barThickness: 48
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.label === 'Remaining') return '';
                                return `${context.raw} tickete`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false, drawBorder: false },
                        border: { display: false },
                        ticks: { color: '#8a8a8a', font: { size: 14 } }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        max: <?= json_encode($maxTicketsForChart) ?>,
                        grid: { color: '#ececec', drawBorder: false },
                        border: { display: false },
                        ticks: {
                            color: '#8a8a8a',
                            font: { size: 14 },
                            stepSize: Math.ceil(<?= json_encode($maxTicketsForChart) ?> / 4)
                        }
                    }
                }
            }
        });
    </script>

    <script>
        const fullFlightScheduleLabels = <?= json_encode($flightScheduleLabels) ?>;
        const fullDomesticSeries = <?= json_encode($domesticSeries) ?>;
        const fullInternationalSeries = <?= json_encode($internationalSeries) ?>;
        const fullTotalFlightsPerMonth = <?= json_encode($totalFlightsPerMonth) ?>;

        const scheduleCanvas = document.getElementById('flightScheduleChart');
        const scheduleRangeSelect = document.getElementById('scheduleRangeSelect');

        function getLastNMonthsData(n) {
            return {
                labels: fullFlightScheduleLabels.slice(-n),
                domestic: fullDomesticSeries.slice(-n),
                international: fullInternationalSeries.slice(-n),
                total: fullTotalFlightsPerMonth.slice(-n)
            };
        }

        function getHighlightIndex(totalArray) {
            const maxValue = Math.max(...totalArray);
            return totalArray.indexOf(maxValue);
        }

        const initialRange = parseInt(scheduleRangeSelect.value, 10);
        const initialData = getLastNMonthsData(initialRange);
        const initialHighlightIndex = getHighlightIndex(initialData.total);

        const flightScheduleCtx = scheduleCanvas.getContext('2d');

        const highlightPlugin = {
            id: 'highlightPlugin',
            afterDatasetsDraw(chart) {
                const highlightIndex = chart.options.plugins.highlightPlugin.highlightIndex;
                if (highlightIndex < 0) return;

                const ctx = chart.ctx;
                const xScale = chart.scales.x;
                const yScale = chart.scales.y;
                const x = xScale.getPixelForValue(highlightIndex);

                ctx.save();
                ctx.setLineDash([6, 6]);
                ctx.strokeStyle = '#d8b75b';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(x, yScale.top);
                ctx.lineTo(x, yScale.bottom);
                ctx.stroke();
                ctx.restore();
            }
        };

        const flightScheduleChart = new Chart(flightScheduleCtx, {
            type: 'line',
            data: {
                labels: initialData.labels,
                datasets: [
                    {
                        label: 'Domestic',
                        data: initialData.domestic,
                        borderColor: '#d8b75b',
                        backgroundColor: 'rgba(216, 183, 91, 0.10)',
                        fill: true,
                        tension: 0.45,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#d8b75b',
                        pointBorderWidth: 0,
                        borderWidth: 3
                    },
                    {
                        label: 'International',
                        data: initialData.international,
                        borderColor: '#1f1f1f',
                        backgroundColor: 'rgba(31, 31, 31, 0.05)',
                        fill: true,
                        tension: 0.45,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#1f1f1f',
                        pointBorderWidth: 0,
                        borderWidth: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 1200, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    highlightPlugin: { highlightIndex: initialHighlightIndex },
                    tooltip: {
                        backgroundColor: '#1f1f1f',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#d8b75b',
                        borderWidth: 1,
                        padding: 14,
                        displayColors: true,
                        callbacks: {
                            title: function(context) {
                                return context[0].label + ' 2026';
                            },
                            label: function(context) {
                                return `${context.dataset.label}: ${context.raw} zboruri`;
                            },
                            afterBody: function(context) {
                                const index = context[0].dataIndex;
                                const total = chartTotalAtIndex(index, context[0].chart);
                                return `Total: ${total} zboruri`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        border: { display: false },
                        ticks: { color: '#8a8a8a', font: { size: 14 } }
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: Math.max(...initialData.total, 10) + 2,
                        grid: { color: '#ececec', drawBorder: false },
                        border: { display: false },
                        ticks: { color: '#8a8a8a', font: { size: 14 } }
                    }
                }
            },
            plugins: [highlightPlugin]
        });

        function chartTotalAtIndex(index, chart) {
            const domestic = chart.data.datasets[0].data[index] || 0;
            const international = chart.data.datasets[1].data[index] || 0;
            return domestic + international;
        }

        scheduleRangeSelect.addEventListener('change', function() {
            const range = parseInt(this.value, 10);
            const updatedData = getLastNMonthsData(range);
            const highlightIndex = getHighlightIndex(updatedData.total);

            flightScheduleChart.data.labels = updatedData.labels;
            flightScheduleChart.data.datasets[0].data = updatedData.domestic;
            flightScheduleChart.data.datasets[1].data = updatedData.international;

            flightScheduleChart.options.plugins.highlightPlugin.highlightIndex = highlightIndex;
            flightScheduleChart.options.scales.y.suggestedMax = Math.max(...updatedData.total, 10) + 2;

            flightScheduleChart.update();
        });
    </script>
</body>
</html>