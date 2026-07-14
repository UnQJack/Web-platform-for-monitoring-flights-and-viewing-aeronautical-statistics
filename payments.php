<?php
session_start();
require_once 'db.php';

$paymentsSql = "
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
    ORDER BY b.created_at DESC
";

$result = $conn->query($paymentsSql);
$payments = [];

while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

$statsSql = "
    SELECT
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN price ELSE 0 END), 0) AS paid_total,
        COALESCE(SUM(CASE WHEN status = 'Pending' THEN price ELSE 0 END), 0) AS pending_total,
        COALESCE(SUM(CASE WHEN status = 'Cancelled' THEN price ELSE 0 END), 0) AS cancelled_total,
        COALESCE(SUM(price), 0) AS all_total,
        COUNT(*) AS total_payments,
        SUM(status = 'Paid') AS paid_count,
        SUM(status = 'Pending') AS pending_count,
        SUM(status = 'Cancelled') AS cancelled_count
    FROM bookings
";

$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

$allTotal = (float)($stats['all_total'] ?? 0);
$paidTotal = (float)($stats['paid_total'] ?? 0);
$pendingTotal = (float)($stats['pending_total'] ?? 0);
$cancelledTotal = (float)($stats['cancelled_total'] ?? 0);

$paidPercent = $allTotal > 0 ? round(($paidTotal / $allTotal) * 100, 1) : 0;
$pendingPercent = $allTotal > 0 ? round(($pendingTotal / $allTotal) * 100, 1) : 0;
$cancelledPercent = $allTotal > 0 ? round(($cancelledTotal / $allTotal) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>SkyTix - Plati</title>

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
    font-weight: 800;
}

.content-grid {
    display: grid;
    grid-template-columns: 1.8fr 0.8fr;
    gap: 20px;
}

.transactions-card,
.summary-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
    height: fit-content;
}

.section-title {
    font-size: 28px;
    font-weight: 900;
    margin-bottom: 18px;
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

.transaction-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.transaction-item {
    display: grid;
    grid-template-columns: 52px 1fr auto;
    gap: 16px;
    align-items: center;
    background: #fafafa;
    border-radius: 18px;
    padding: 16px;
    border: 1px solid #eeeeee;
}

.transaction-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: #e4d09c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 20px;
}

.transaction-main strong {
    display: block;
    font-size: 17px;
    margin-bottom: 5px;
}

.transaction-main span {
    color: #8a8a8a;
    font-size: 14px;
}

.transaction-side {
    text-align: right;
}

.amount {
    font-size: 18px;
    font-weight: 900;
    margin-bottom: 8px;
}

.status-badge {
    display: inline-block;
    padding: 8px 13px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 800;
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

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 16px 0;
    border-bottom: 1px solid #ececec;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-row span {
    color: #8a8a8a;
}

.summary-row strong {
    font-weight: 900;
}

.empty-text {
    color: #8a8a8a;
    padding: 20px 0;
}

@media (max-width: 1200px) {
    .payments-layout,
    .content-grid {
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

    .transaction-item {
        grid-template-columns: 1fr;
    }

    .transaction-side {
        text-align: left;
    }
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
                <li class="active"><a href="payments.php">Plati</a></li>
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
                <h2>Plati</h2>
            </div>

            <section class="cards">
    <div class="card">
        <small>Total Incasat</small>
        <div class="value"><?= number_format($paidTotal, 2) ?> RON</div>
    </div>

    <div class="card">
        <small>In Asteptare</small>
        <div class="value"><?= number_format($pendingTotal, 2) ?> RON</div>
    </div>

    <div class="card">
        <small>Anulate</small>
        <div class="value"><?= number_format($cancelledTotal, 2) ?> RON</div>
    </div>

    <div class="card">
        <small>Total Tranzactii</small>
        <div class="value"><?= (int)$stats['total_payments'] ?></div>
    </div>
</section>

            <section class="content-grid">
                <div class="transactions-card">
                    <div class="section-title">Tranzactii Recente</div>

                    <div class="filters-bar">
                        <input type="text" id="paymentSearch" placeholder="Cauta utilizator, email, zbor, companie...">

                        <select id="paymentStatusFilter">
                            <option value="">Toate starile</option>
                            <option value="paid">Platita</option>
                            <option value="pending">In asteptare</option>
                            <option value="cancelled">Anulata</option>
                        </select>
                    </div>

                    <?php if (!empty($payments)): ?>
                        <div class="transaction-list">
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $status = strtolower($payment['status']);
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

                                <div
                                    class="transaction-item"
                                    data-search="<?= strtolower(htmlspecialchars(
                                        $payment['user_name'] . ' ' .
                                        $payment['email'] . ' ' .
                                        $payment['flight_number'] . ' ' .
                                        $payment['airline_name'] . ' ' .
                                        $payment['origin_code'] . ' ' .
                                        $payment['destination_code']
                                    )) ?>"
                                    data-status="<?= strtolower(htmlspecialchars($payment['status'])) ?>"
                                >
                                    <div class="transaction-icon">RON</div>

                                    <div class="transaction-main">
                                        <strong><?= htmlspecialchars($payment['user_name']) ?> · <?= htmlspecialchars($payment['flight_number']) ?></strong>
                                        <span>
                                            <?= htmlspecialchars($payment['email']) ?> ·
                                            <?= htmlspecialchars($payment['airline_name']) ?> ·
                                            <?= htmlspecialchars($payment['origin_code']) ?> → <?= htmlspecialchars($payment['destination_code']) ?>
                                        </span>
                                        <br>
                                        <span><?= htmlspecialchars($payment['created_at']) ?></span>
                                    </div>

                                    <div class="transaction-side">
                                        <div class="amount"><?= number_format((float)$payment['price'], 2) ?></div>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-text">Nu exista tranzactii in baza de date.</div>
                    <?php endif; ?>
                </div>

                <div class="summary-card">
                    <div class="section-title">Sumar Financiar</div>

                    <div class="summary-row">
                        <span>Rezervari platite</span>
                        <strong><?= (int)($stats['paid_count'] ?? 0) ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Rezervari in asteptare</span>
                        <strong><?= (int)($stats['pending_count'] ?? 0) ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Rezervari anulate</span>
                        <strong><?= (int)($stats['cancelled_count'] ?? 0) ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Total potential</span>
                        <strong><?= number_format($allTotal, 2) ?> RON</strong>
                    </div>

                    <div class="summary-row">
                        <span>Rata incasare</span>
                        <strong><?= $paidPercent ?>%</strong>
                    </div>

                    <div class="summary-row">
                        <span>Rata in asteptare</span>
                        <strong><?= $pendingPercent ?>%</strong>
                    </div>

                    <div class="summary-row">
                        <span>Rata anulare</span>
                        <strong><?= $cancelledPercent ?>%</strong>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
const paymentSearch = document.getElementById('paymentSearch');
const paymentStatusFilter = document.getElementById('paymentStatusFilter');

function filterPayments() {
    const searchValue = paymentSearch.value.toLowerCase();
    const statusValue = paymentStatusFilter.value;

    document.querySelectorAll('.transaction-item').forEach(item => {
        const itemSearch = item.dataset.search || '';
        const itemStatus = item.dataset.status || '';

        const matchesSearch = itemSearch.includes(searchValue);
        const matchesStatus = statusValue === '' || itemStatus === statusValue;

        item.style.display = matchesSearch && matchesStatus ? 'grid' : 'none';
    });
}

paymentSearch.addEventListener('input', filterPayments);
paymentStatusFilter.addEventListener('change', filterPayments);
</script>

</body>
</html>