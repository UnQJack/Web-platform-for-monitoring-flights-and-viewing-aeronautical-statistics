<?php
session_start();
require_once 'db.php';
require_once 'add_notification.php';
require_once 'send_email_notification.php';

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_status']) && $isAdmin) {
    $bookingId = (int)$_POST['booking_id'];
    $newStatus = $_POST['status'];

    $allowedStatuses = ['Paid', 'Pending', 'Cancelled'];

    if ($bookingId > 0 && in_array($newStatus, $allowedStatuses)) {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $bookingId);
        $stmt->execute();
        addNotification(
            $conn,
            'bookings',
            'Actualizare',
            'Starea rezervarii cu ID #' . $bookingId . ' a fost actualizata la ' . $newStatus . '.'
        );
        $emailSql = "
            SELECT
                u.email,
                u.name,
                f.flight_number,
                a.name AS airline_name,
                ao.iata_code AS origin_code,
                ad.iata_code AS destination_code
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports ao ON f.origin_airport_id = ao.id
            JOIN airports ad ON f.destination_airport_id = ad.id
            WHERE b.id = ?
        ";

        $emailStmt = $conn->prepare($emailSql);
        $emailStmt->bind_param("i", $bookingId);
        $emailStmt->execute();

        $emailData = $emailStmt->get_result()->fetch_assoc();

        $emailStmt->close();

        if ($emailData) {

            $statusText = $newStatus;

            if ($newStatus === 'Paid') {
                $statusText = 'Platita';
            } elseif ($newStatus === 'Pending') {
                $statusText = 'In asteptare';
            } elseif ($newStatus === 'Cancelled') {
                $statusText = 'Anulata';
            }

            $subject = "SkyTix - Stare rezervare actualizata";

            $message = "
                <h2>Stare rezervare modificata</h2>
                <p>Buna, " . htmlspecialchars($emailData['name']) . "!</p>
                <p>Starea rezervarii tale a fost actualizata.</p>
                <p><strong>Zbor:</strong> " . htmlspecialchars($emailData['flight_number']) . "</p>
                <p><strong>Companie:</strong> " . htmlspecialchars($emailData['airline_name']) . "</p>
                <p><strong>Ruta:</strong>
                    " . htmlspecialchars($emailData['origin_code']) . "
                    →
                    " . htmlspecialchars($emailData['destination_code']) . "
                </p>
                <p><strong>Stare noua:</strong> " . $statusText . "</p>
                <p>Multumim ca folosesti SkyTix!</p>
            ";

            sendEmailNotification(
                $conn,
                $emailData['email'],
                $subject,
                $message
            );
        }
        $stmt->close();
    }
    header("Location: bookings.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking_id'])) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: bookings.php");
        exit;
    }

    $deleteBookingId = (int)$_POST['delete_booking_id'];

    $deleteStmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $deleteStmt->bind_param("i", $deleteBookingId);
    $deleteStmt->execute();
    addNotification(
        $conn,
        'bookings',
        'Stergere',
        'A fost stearsa rezervarea cu ID #' . $deleteBookingId . '.'
    );
    $deleteStmt->close();

    header("Location: bookings.php");
    exit;
}

$bookingStatsSql = "
    SELECT
        COUNT(*) AS total_bookings,
        SUM(status = 'Paid') AS paid_bookings,
        SUM(status = 'Pending') AS pending_bookings,
        SUM(status = 'Cancelled') AS cancelled_bookings
    FROM bookings
";
$bookingStatsStmt = $conn->query($bookingStatsSql);
$bookingStats = $bookingStatsStmt->fetch_assoc();

$bookingsSql = "
    SELECT
        b.id,
        u.name AS user_name,
        u.email,
        f.flight_number,
        a.name AS airline_name,
        ao.iata_code AS origin_code,
        ad.iata_code AS destination_code,
        b.price,
        b.status,
        b.created_at
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN flights f ON b.flight_id = f.id
    JOIN airlines a ON f.airline_id = a.id
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id
    ORDER BY b.created_at ASC
";
$bookingsStmt = $conn->query($bookingsSql);
$bookings = [];

while ($row = $bookingsStmt->fetch_assoc()) {
    $bookings[] = $row;
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skytix - Rezervari</title>
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

        .table-card {
            background: white;
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.04);
        }

        .table-title {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table th,
        .bookings-table td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid #ececec;
            font-size: 15px;
        }

        .bookings-table th {
            color: #8a8a8a;
            font-weight: 600;
            background: #fafafa;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .status-paid {
            background: #e4d09c;
            color: #1f1f1f;
        }

        .status-pending {
            background: #f5edd1;
            color: #7a5a1a;
        }

        .status-cancelled {
            background: #1f1f1f;
            color: #ffffff;
        }

        .price {
            font-weight: 700;
        }

        .route {
            font-weight: 600;
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

            .bookings-table {
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

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .search {
                width: 100%;
            }
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }

        .add-btn:hover {
            background: #cfae4f;
        }

        .filters-bar {
            display: flex;
            gap: 14px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filters-bar input,
        .filters-bar select {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 15px;
            outline: none;
        }

        .filters-bar input {
            min-width: 320px;
            flex: 1;
        }

        .filters-bar select {
            min-width: 180px;
        }

        .status-form {
            margin: 0;
        }

        .booking-status-select {
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            outline: none;
        }

        .booking-status-select.paid {
            background: #e4d09c;
            color: #1f1f1f;
        }

        .booking-status-select.pending {
            background: #f5edd1;
            color: #7a5a1a;
        }

        .booking-status-select.cancelled {
            background: #1f1f1f;
            color: #ffffff;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
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
                    <li class="active"><a href="bookings.php">Rezervari</a></li>
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
            </aside>

            <main class="main">
                <div class="topbar">
                    <h2>Rezervari</h2>
                </div>

                <section class="cards">
                    <div class="card">
                        <small>Total Rezervari</small>
                        <div class="value"><?= (int)($bookingStats['total_bookings'] ?? 0) ?></div>
                    </div>

                    <div class="card">
                        <small>Rezervari Platite</small>
                        <div class="value"><?= (int)($bookingStats['paid_bookings'] ?? 0) ?></div>
                    </div>

                    <div class="card">
                        <small>Rezervari in Asteptare</small>
                        <div class="value"><?= (int)($bookingStats['pending_bookings'] ?? 0) ?></div>
                    </div>

                    <div class="card">
                        <small>Rezervari Anulate</small>
                        <div class="value"><?= (int)($bookingStats['cancelled_bookings'] ?? 0) ?></div>
                    </div>
                </section>

                <section class="table-card">
                    <div class="table-header">
                        <h2>Lista Rezervarilor</h2>
                        <div class="header-actions">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="add_booking.php" class="add-btn">
                                    Adauga
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="filters-bar">
                        <input type="text" id="searchInput" placeholder="Cauta utilizator, email, zbor, ruta...">

    <select id="statusFilter">
        <option value="">Toate starile</option>
        <option value="paid">Platita</option>
        <option value="pending">In asteptare</option>
        <option value="cancelled">Anulata</option>
    </select>

    <select id="companyFilter">
        <option value="">Toate companiile</option>
        <?php
        $companies = [];
        foreach ($bookings as $booking) {
            $companies[$booking['airline_name']] = $booking['airline_name'];
        }
        foreach ($companies as $company):
        ?>
            <option value="<?= strtolower(htmlspecialchars($company)) ?>">
                <?= htmlspecialchars($company) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                    <?php if (!empty($bookings)): ?>
                        <table class="bookings-table">
                            <thead>
                                <tr 
                                    data-search="<?= strtolower(htmlspecialchars($booking['user_name'] . ' ' . $booking['email'] . ' ' . $booking['flight_number'] . ' ' . $booking['origin_code'] . ' ' . $booking['destination_code'])) ?>"
                                    data-status="<?= strtolower(htmlspecialchars($booking['status'])) ?>"
                                    data-company="<?= strtolower(htmlspecialchars($booking['airline_name'])) ?>"
                                >
                                    <th>ID</th>
                                    <th>Utilizator</th>
                                    <th>Email</th>
                                    <th>Zbor</th>
                                    <th>Companie</th>
                                    <th>Ruta</th>
                                    <th>Pret</th>
                                    <th>Stare</th>
                                    <th>Data Rezervarii</th>
                                    <?php if ($isAdmin): ?>
                                        <th>Actiuni</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr 
                                        data-search="<?= strtolower(htmlspecialchars($booking['user_name'] . ' ' . $booking['email'] . ' ' . $booking['flight_number'] . ' ' . $booking['origin_code'] . ' ' . $booking['destination_code'])) ?>"
                                        data-status="<?= strtolower(htmlspecialchars($booking['status'])) ?>"
                                        data-company="<?= strtolower(htmlspecialchars($booking['airline_name'])) ?>"
                                    >
                                        <td>#<?= (int)$booking['id'] ?></td>
                                        <td><?= htmlspecialchars($booking['user_name']) ?></td>
                                        <td><?= htmlspecialchars($booking['email']) ?></td>
                                        <td><?= htmlspecialchars($booking['flight_number']) ?></td>
                                        <td><?= htmlspecialchars($booking['airline_name']) ?></td>
                                        <td class="route">
                                            <?= htmlspecialchars($booking['origin_code']) ?> → <?= htmlspecialchars($booking['destination_code']) ?>
                                        </td>
                                        <td class="price"><?= number_format((float)$booking['price'], 2) ?> RON</td>
                                        <td>
                                            <?php
                                            $status = strtolower($booking['status']);
                                            $statusClass = 'status-pending';
                                            $statusText = 'In Asteptare';
                                            if ($status === 'paid') {
                                                $statusClass = 'status-paid';
                                                $statusText = 'Platita';
                                            } elseif ($status === 'cancelled') {
                                                $statusClass = 'status-cancelled';
                                                $statusText = 'Anulata';
                                            }
                                            ?>
                                            <?php if ($isAdmin): ?>
        <form method="POST" class="status-form">
        <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
        <input type="hidden" name="update_booking_status" value="1">

        <select name="status" class="booking-status-select <?= strtolower($booking['status']) ?>" onchange="this.form.submit()">
            <option value="Paid" <?= $booking['status'] === 'Paid' ? 'selected' : '' ?>>Platita</option>
            <option value="Pending" <?= $booking['status'] === 'Pending' ? 'selected' : '' ?>>In Asteptare</option>
            <option value="Cancelled" <?= $booking['status'] === 'Cancelled' ? 'selected' : '' ?>>Anulata</option>
        </select>
    </form>
<?php else: ?>
    <span class="status-badge <?= $statusClass ?>">
        <?= $statusText ?>
    </span>
<?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($booking['created_at']) ?></td>

                                        <?php if ($isAdmin): ?>
                                            <td>
                                                <div class="actions-cell">
                                                    <a href="edit_booking.php?id=<?= (int)$booking['id'] ?>" class="edit-btn">
                                                        Editeaza
                                                    </a>
                                                    <form method="POST" onsubmit="return confirm('Sigur vrei sa stergi aceasta rezervare?');">
                                                        <input type="hidden" name="delete_booking_id" value="<?= (int)$booking['id'] ?>">
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
                        <div class="empty-text">Nu exista inca rezervari in baza de date.</div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
    <script>
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const companyFilter = document.getElementById('companyFilter');

    function filterBookings() {
        const searchValue = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value;
        const companyValue = companyFilter.value;

        const rows = document.querySelectorAll('.bookings-table tbody tr');

        rows.forEach(row => {
            const rowSearch = row.dataset.search || '';
            const rowStatus = row.dataset.status || '';
            const rowCompany = row.dataset.company || '';

            const matchesSearch = rowSearch.includes(searchValue);
            const matchesStatus = statusValue === '' || rowStatus === statusValue;
            const matchesCompany = companyValue === '' || rowCompany === companyValue;

            row.style.display = matchesSearch && matchesStatus && matchesCompany ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterBookings);
    statusFilter.addEventListener('change', filterBookings);
    companyFilter.addEventListener('change', filterBookings);
</script>
</body>
</html>